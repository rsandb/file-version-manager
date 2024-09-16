<?php
namespace LVAI\FileVersionManager;

class CategoryManager {
	private $wpdb;
	private $table_name;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
		$this->table_name = $wpdb->prefix . Constants::CAT_TABLE_NAME;
	}

	public function add_category( $cat_name, $cat_slug, $cat_parent_id, $cat_description ) {
		if ( empty( $cat_slug ) ) {
			$cat_slug = sanitize_title( $cat_name );
		}

		return $this->wpdb->insert(
			$this->table_name,
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
			$this->table_name,
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
			$this->table_name,
			[ 'id' => $category_id ],
			[ '%d' ]
		);
	}

	public function bulk_delete_categories( $category_ids ) {
		$deleted_count = 0;
		foreach ( $category_ids as $category_id ) {
			$delete_result = $this->delete_category( $category_id );
			if ( $delete_result !== false ) {
				$deleted_count++;
			}
		}
		return $deleted_count;
	}

	public function get_categories( $search = '', $orderby = 'cat_name', $order = 'ASC' ) {
		$query = "SELECT c.*, COUNT(f.id) as file_count 
                  FROM {$this->table_name} c 
                  LEFT JOIN {$this->wpdb->prefix}" . Constants::FILE_TABLE_NAME . " f ON c.id = f.file_category_id";

		if ( ! empty( $search ) ) {
			$query .= $this->wpdb->prepare( " WHERE c.cat_name LIKE %s", '%' . $this->wpdb->esc_like( $search ) . '%' );
		}

		$query .= " GROUP BY c.id ORDER BY " . esc_sql( $orderby ) . " " . esc_sql( $order );

		return $this->wpdb->get_results( $query, ARRAY_A );
	}

	public function get_categories_hierarchical( $parent_id = 0 ) {
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
}