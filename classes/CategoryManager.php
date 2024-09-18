<?php
namespace LVAI\FileVersionManager;

class CategoryManager {
	private $wpdb;
	private $category_table_name;
	private $file_table_name;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
		$this->category_table_name = $wpdb->prefix . Constants::CAT_TABLE_NAME;
		$this->file_table_name = $wpdb->prefix . Constants::FILE_TABLE_NAME;
	}

	public function add_category( $cat_name, $cat_slug, $cat_parent_id, $cat_description ) {
		if ( empty( $cat_slug ) ) {
			$cat_slug = sanitize_title( $cat_name );
		}

		return $this->wpdb->insert(
			$this->category_table_name,
			[ 
				'cat_name' => $cat_name,
				'cat_slug' => $cat_slug,
				'cat_parent_id' => $cat_parent_id,
				'cat_description' => $cat_description,
			],
			[ '%s', '%s', '%d', '%s' ]
		);
	}

	public function update_category( $category_id, $cat_name, $cat_description, $cat_parent_id ) {
		return $this->wpdb->update(
			$this->category_table_name,
			[ 
				'cat_name' => $cat_name,
				'cat_description' => $cat_description,
				'cat_parent_id' => $cat_parent_id,
			],
			[ 'id' => $category_id ],
			[ '%s', '%s', '%d' ],
			[ '%d' ]
		);
	}

	public function delete_category( $category_id ) {
		return $this->wpdb->delete(
			$this->category_table_name,
			[ 'id' => $category_id ],
			[ '%d' ]
		);
	}

	public function get_categories( $search = '', $orderby = 'cat_name', $order = 'ASC' ) {
		$allowed_orderby = [ 'cat_name', 'id', 'cat_parent_id' ];
		$allowed_order = [ 'ASC', 'DESC' ];

		$orderby = in_array( $orderby, $allowed_orderby ) ? $orderby : 'cat_name';
		$order = in_array( strtoupper( $order ), $allowed_order ) ? strtoupper( $order ) : 'ASC';

		$query = "SELECT c.*, COUNT(f.id) as total_files
				  FROM {$this->category_table_name} c 
				  LEFT JOIN {$this->file_table_name} f ON c.id = f.file_category_id
				  WHERE 1=1";

		$params = [];
		if ( ! empty( $search ) ) {
			$query .= " AND c.cat_name LIKE %s";
			$params[] = '%' . $this->wpdb->esc_like( $search ) . '%';
		}

		$query .= " GROUP BY c.id ORDER BY {$orderby} {$order}";

		$prepared_query = $params ? $this->wpdb->prepare( $query, $params ) : $query;
		$categories = $this->wpdb->get_results( $prepared_query, ARRAY_A );

		return $this->build_category_tree( $categories );
	}

	public function build_category_tree( $categories, $parent_id = 0, $level = 0 ) {
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

	public function get_categories_hierarchical( $parent_id = 0 ) {
		$query = $this->wpdb->prepare(
			"SELECT id, cat_name, cat_parent_id FROM %i WHERE cat_parent_id = %d ORDER BY cat_name ASC", $this->category_table_name, $parent_id
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
}