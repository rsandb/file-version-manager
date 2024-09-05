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
		add_screen_option( 'per_page', [ 
			'label' => 'Categories per page',
			'default' => 20,
			'option' => 'fvm_categories_per_page',
		] );
	}

	public function display_admin_page() {
		ob_start();
		?>
		<div class="wrap">
			<h1>File Categories</h1>
			<form method="post">
				<?php
				wp_nonce_field( 'bulk-categories' );
				$this->wp_list_table->prepare_items();
				$this->wp_list_table->display_bulk_action_result();
				$this->wp_list_table->search_box( 'Search', 'search' );
				$this->wp_list_table->display();
				?>
			</form>
		</div>
		<?php
		ob_end_flush();
	}
}