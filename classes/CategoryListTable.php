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
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( $_REQUEST['orderby'] ) : 'cat_name';
		$order = isset( $_REQUEST['order'] ) ? sanitize_key( $_REQUEST['order'] ) : 'asc';

		$this->set_pagination_args( [ 
			'total_items' => $this->get_total_items( $search ),
			'per_page' => $per_page,
		] );

		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
		$this->items = $this->get_categories( $per_page, $current_page, $orderby, $order, $search );

		$this->process_bulk_action();
	}

	public function get_categories( $per_page, $current_page, $orderby = 'cat_name', $order = 'asc', $search = '' ) {
		global $wpdb;
		$table_name = $this->get_table_name();
		$offset = ( $current_page - 1 ) * $per_page;

		$orderby = $this->sanitize_orderby( $orderby );
		$order = $this->sanitize_order( $order );

		$where_clause = $search ? $wpdb->prepare( "WHERE cat_name LIKE %s", '%' . $wpdb->esc_like( $search ) . '%' ) : '';

		$query = $wpdb->prepare(
			"SELECT c.*, COUNT(f.id) as total_files 
            FROM $table_name c 
            LEFT JOIN {$wpdb->prefix}" . Constants::FILE_TABLE_NAME . " f ON c.id = f.file_category_id 
            $where_clause 
            GROUP BY c.id 
            ORDER BY $orderby $order 
            LIMIT %d OFFSET %d",
			$per_page, $offset
		);

		return $wpdb->get_results( $query, ARRAY_A );
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
		// This method can remain largely unchanged
		// ...
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
			'cat_name' => 'Category Name',
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
		$actions = [ 
			'id' => sprintf( '<span>ID: %d</span>', $item['id'] ),
			'edit' => sprintf( '<a href="#" class="edit-category" data-category-id="%d">Edit</a>', $item['id'] ),
			'delete' => sprintf( '<a href="%s" onclick="return confirm(\'Are you sure you want to delete this category?\')">Delete</a>', wp_nonce_url( admin_url( 'admin.php?page=fvm_categories&action=delete&category_id=' . $item['id'] ), 'delete_category_' . $item['id'] ) ),
		];

		return sprintf(
			'%s %s',
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