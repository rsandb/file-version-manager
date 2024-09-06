<?php
namespace LVAI\FileVersionManager;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class CategoryListTable extends \WP_List_Table {
	private $category_manager;

	public function __construct( $category_manager ) {
		parent::__construct( [ 
			'singular' => 'category',
			'plural' => 'categories',
			'ajax' => false,
		] );
		$this->category_manager = $category_manager;
	}

	private function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . Constants::CAT_TABLE_NAME;
	}

	public function prepare_items() {
		if ( ! $this->ensure_table_exists() ) {
			return;
		}

		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( $_REQUEST['s'] ) : '';

		$per_page = $this->get_items_per_page( 'fvm_categories_per_page', 20 );
		$current_page = $this->get_pagenum();

		$orderby = isset( $_REQUEST['orderby'] ) ? $this->sanitize_orderby( $_REQUEST['orderby'] ) : 'cat_name';
		$order = isset( $_REQUEST['order'] ) ? $this->sanitize_order( $_REQUEST['order'] ) : 'ASC';

		$all_categories = $this->get_categories( $search, $orderby, $order );
		$total_items = count( $all_categories );

		$this->set_pagination_args( [ 
			'total_items' => $total_items,
			'per_page' => $per_page,
		] );

		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];

		$offset = ( $current_page - 1 ) * $per_page;
		$this->items = array_slice( $all_categories, $offset, $per_page );

		$this->process_bulk_action();
	}

	public function get_categories( $search = '', $orderby = 'cat_name', $order = 'ASC' ) {
		global $wpdb;
		$table_name = $this->get_table_name();

		$where_clause = $search ? $wpdb->prepare( "WHERE c.cat_name LIKE %s", '%' . $wpdb->esc_like( $search ) . '%' ) : '';

		// Update the ORDER BY clause in the query
		$query = "SELECT c.*, COUNT(f.id) as total_files 
			FROM $table_name c 
			LEFT JOIN {$wpdb->prefix}" . Constants::FILE_TABLE_NAME . " f ON c.id = f.file_category_id 
			$where_clause 
			GROUP BY c.id 
			ORDER BY c.cat_parent_id ASC, " . esc_sql( $orderby ) . " " . esc_sql( $order );

		$categories = $wpdb->get_results( $query, ARRAY_A );
		return $this->build_category_tree( $categories );
	}

	private function build_category_tree( $categories, $parent_id = 0, $level = 0 ) {
		$tree = [];
		foreach ( $categories as $category ) {
			if ( $category['cat_parent_id'] == $parent_id ) {
				$category['level'] = $level;
				$tree[] = $category;
				$tree = array_merge( $tree, $this->build_category_tree( $categories, $category['id'], $level + 1 ) );
			}
		}
		return $tree;
	}

	public function get_bulk_actions() {
		return [ 
			'delete' => 'Delete',
		];
	}

	public function get_total_items( $search = '' ) {
		global $wpdb;
		$table_name = $this->get_table_name();

		$search_query = '';
		if ( ! empty( $search ) ) {
			$search_query = $wpdb->prepare( "WHERE cat_name LIKE %s", '%' . $wpdb->esc_like( $search ) . '%', '%' . $wpdb->esc_like( $search ) . '%' );
		}

		return $wpdb->get_var( "SELECT COUNT(*) FROM $table_name $search_query" );
	}

	public function search_box( $text, $input_id ) {
		if ( empty( $_REQUEST['s'] ) && ! $this->has_items() ) {
			return;
		}

		$input_id = $input_id . '-search-input';

		if ( ! empty( $_REQUEST['orderby'] ) ) {
			echo '<input type="hidden" name="orderby" value="' . esc_attr( $_REQUEST['orderby'] ) . '" />';
		}
		if ( ! empty( $_REQUEST['order'] ) ) {
			echo '<input type="hidden" name="order" value="' . esc_attr( $_REQUEST['order'] ) . '" />';
		}
		if ( ! empty( $_REQUEST['post_mime_type'] ) ) {
			echo '<input type="hidden" name="post_mime_type" value="' . esc_attr( $_REQUEST['post_mime_type'] ) . '" />';
		}
		if ( ! empty( $_REQUEST['detached'] ) ) {
			echo '<input type="hidden" name="detached" value="' . esc_attr( $_REQUEST['detached'] ) . '" />';
		}
		?>
		<p class="search-box">
			<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo $text; ?>:</label>
			<input type="search" id="<?php echo esc_attr( $input_id ); ?>" name="s" value="<?php _admin_search_query(); ?>" />
			<?php submit_button( $text, '', '', false, array( 'id' => 'search-submit' ) ); ?>
		</p>
		<?php
	}

	private function ensure_table_exists() {
		global $wpdb;
		$table_name = $this->get_table_name();
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
			error_log( "Table $table_name does not exist." );
			return false;
		}
		return true;
	}

	public function get_columns() {
		return [ 
			'cb' => '<input type="checkbox" />',
			'cat_name' => 'Name',
			'cat_description' => 'Description',
			'cat_parent_id' => 'Parent Category',
			'total_files' => 'Count',
		];
	}

	public function get_sortable_columns() {
		return [ 
			'cat_name' => [ 'cat_name', true ],
		];
	}

	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'cat_description':
				return empty( $item[ $column_name ] ) ? '—' : esc_html( $item[ $column_name ] );
			case 'cat_parent_id':
				return $this->get_parent_category_name( $item[ $column_name ] );
			case 'total_files':
				return intval( $item['total_files'] );
			default:
				return esc_html( $item[ $column_name ] ?? '' );
		}
	}

	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="category[]" value="%s" />',
			$item['id']
		);
	}

	public function column_cat_name( $item ) {
		$indent = str_repeat( '— ', $item['level'] );
		$actions = [ 
			'id' => sprintf( '<span>ID: %d</span>', $item['id'] ),
			'edit' => sprintf( '<a href="#" class="edit-category" data-category-id="%d">Edit</a>', $item['id'] ),
			'delete' => sprintf( '<a href="%s" onclick="return confirm(\'Are you sure you want to delete this category?\')">Delete</a>', wp_nonce_url( admin_url( 'admin.php?page=fvm_categories&action=delete&category_id=' . $item['id'] ), 'delete_category_' . $item['id'] ) ),
		];

		return sprintf(
			'%s%s %s',
			$indent,
			$item['cat_name'],
			$this->row_actions( $actions )
		);
	}

	private function get_parent_category_name( $parent_id ) {
		if ( $parent_id == 0 ) {
			return '—';
		}

		global $wpdb;
		$table_name = $this->get_table_name();
		$parent_name = $wpdb->get_var( $wpdb->prepare( "SELECT cat_name FROM $table_name WHERE id = %d", $parent_id ) );

		return $parent_name ? $parent_name : 'Unknown';
	}

	private function sanitize_orderby( $orderby ) {
		$allowed_columns = array_keys( $this->get_sortable_columns() );
		return in_array( $orderby, $allowed_columns ) ? $orderby : 'cat_name';
	}

	private function sanitize_order( $order ) {
		return in_array( strtoupper( $order ), [ 'ASC', 'DESC' ] ) ? strtoupper( $order ) : 'ASC';
	}
}