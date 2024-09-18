<?php
namespace FVM\FileVersionManager;

#todo: category query string in URL returns all files in that category

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class FileListTable extends \WP_List_Table {
	private $file_manager;

	public function __construct( $file_manager ) {
		parent::__construct( [ 
			'singular' => 'file',
			'plural' => 'files',
			'ajax' => false,
		] );
		$this->file_manager = $file_manager;
	}

	private function get_table_name( $table ) {
		global $wpdb;
		if ( $table === 'category' ) {
			return $wpdb->prefix . Constants::CAT_TABLE_NAME;
		} elseif ( $table === 'files' ) {
			return $wpdb->prefix . Constants::FILE_TABLE_NAME;
		}
		return $wpdb->prefix . Constants::CAT_TABLE_NAME;
	}

	public function prepare_items() {
		if ( ! $this->ensure_table_exists() ) {
			return;
		}

		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( $_REQUEST['s'] ) : '';

		$columns = $this->get_columns();
		$sortable = $this->get_sortable_columns();
		$hidden = array();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$per_page = $this->get_items_per_page( 'fvm_files_per_page', 50 );
		$current_page = $this->get_pagenum();
		$total_items = $this->get_total_items( $search );

		$this->set_pagination_args( [ 
			'total_items' => $total_items,
			'per_page' => $per_page,
			'total_pages' => ceil( $total_items / $per_page ),
		] );

		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( $_REQUEST['orderby'] ) : 'date_modified';
		$order = isset( $_REQUEST['order'] ) ? sanitize_key( $_REQUEST['order'] ) : 'desc';

		$this->items = $this->get_files( $per_page, $current_page, $orderby, $order, $search );
	}

	public function get_files( $per_page, $current_page, $orderby = 'date_modified', $order = 'desc', $search = '' ) {
		global $wpdb;
		$file_table_name = $this->get_table_name( 'files' );
		$offset = ( $current_page - 1 ) * $per_page;

		// Validate $orderby to prevent SQL injection
		$allowed_columns = array_keys( $this->get_sortable_columns() );
		if ( ! in_array( $orderby, $allowed_columns ) ) {
			$orderby = 'date_modified';
		}

		// Validate $order
		$order = strtoupper( $order );
		if ( ! in_array( $order, [ 'ASC', 'DESC' ] ) ) {
			$order = 'DESC';
		}

		$search_query = '';
		if ( ! empty( $search ) ) {
			$search_query = $wpdb->prepare( "WHERE file_name LIKE %s OR file_display_name LIKE %s", '%' . $wpdb->esc_like( $search ) . '%', '%' . $wpdb->esc_like( $search ) . '%' );
		}

		$query = "SELECT * FROM $file_table_name $search_query ORDER BY $orderby $order LIMIT %d OFFSET %d";
		$query = $wpdb->prepare( $query, $per_page, $offset );

		$results = $wpdb->get_results( $query, ARRAY_A );

		// Debug: Check if we're getting results
		if ( empty( $results ) ) {
			error_log( "No results found in get_files method. Query: " . $wpdb->last_query );
		}

		return $results;
	}

	public function get_bulk_actions() {
		return [ 
			'delete' => 'Delete',
		];
	}

	public function get_total_items( $search = '' ) {
		global $wpdb;
		$file_table_name = $this->get_table_name( 'files' );

		$search_query = '';
		if ( ! empty( $search ) ) {
			$search_query = $wpdb->prepare( "WHERE file_name LIKE %s OR file_display_name LIKE %s", '%' . $wpdb->esc_like( $search ) . '%', '%' . $wpdb->esc_like( $search ) . '%' );
		}

		return $wpdb->get_var( "SELECT COUNT(*) FROM $file_table_name $search_query" );
	}

	public function search_box( $text, $input_id ) {
		if ( empty( $_REQUEST['s'] ) && ! $this->has_items() ) {
			return;
		}

		$input_id = $input_id . '-search-input';

		?>
		<p class="search-box">
			<label class="screen-reader-text"
				for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $text ); ?>:</label>
			<input type="search" id="<?php echo esc_attr( $input_id ); ?>" name="s"
				value="<?php echo esc_attr( get_query_var( 's' ) ); ?>" />
			<?php submit_button( $text, 'button', false, false, array( 'id' => 'search-submit' ) ); ?>
		</p>
		<?php
	}

	private function ensure_table_exists() {
		global $wpdb;
		$file_table_name = $this->get_table_name( 'files' );
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$file_table_name'" ) != $file_table_name ) {
			error_log( "Table $file_table_name does not exist." );
			return false;
		}
		return true;
	}

	public function get_columns() {
		return [ 
			'cb' => '<input type="checkbox" />',
			'file_name' => 'File Name',
			'file_type' => 'File Type',
			'file_size' => 'Size',
			'file_version' => 'Version',
			'shortcode' => 'Shortcode',
			'date_modified' => 'Date Modified',
		];
	}

	public function get_sortable_columns() {
		return [ 
			'file_name' => [ 'file_name', true ],
			'file_type' => [ 'file_type', true ],
			'file_size' => [ 'file_size', true ],
			'date_modified' => [ 'date_modified', true ],
		];
	}

	public function column_default( $item, $column_name ) {
		// $simplified_file_types = include plugin_dir_path( __FILE__ ) . '../includes/FileTypes.php';

		switch ( $column_name ) {
			case 'file_name':
			case 'file_type':
				return $item[ $column_name ];
			case 'file_version':
				return $item[ $column_name ];
			case 'date_modified':
				return date( 'Y/m/d \a\t g:i a', strtotime( $item[ $column_name ] ) );
			case 'file_size':
				return $this->format_file_size( $item[ $column_name ] );
			case 'shortcode':
				$shortcode = '[fvm id="' . $item['id'] . '"]';
				return sprintf(
					'<div class="shortcode-column-container">
                        <code style="font-size: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 200px;">%s</code>
                        <button type="button" class="button button-small copy-shortcode" data-shortcode="%s">Copy</button>
                    </div>',
					$shortcode,
					esc_attr( $shortcode )
				);
			default:
				return print_r( $item, true );
		}
	}

	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="file[]" value="%s" />',
			$item['id']
		);
	}

	public function column_file_name( $item ) {
		$actions = [ 
			'id' => sprintf( '<span>ID: %d</span>', $item['id'] ),
			'edit' => sprintf( '<a href="#" class="edit-file" data-file-id="%d">Edit</a>', $item['id'] ),
			'view' => sprintf( '<a href="%s" target="_blank">View</a>', esc_url( $item['file_url'] ) ),
			'download' => sprintf( '<a href="%s" download>Download</a>', esc_url( $item['file_url'] ) ),
			'delete' => sprintf( '<a href="%s" onclick="return confirm(\'Are you sure you want to delete this file?\')">Delete</a>', wp_nonce_url( admin_url( 'admin.php?page=fvm_files&action=delete&file_id=' . $item['id'] ), 'delete_file_' . $item['id'] ) ),
		];

		$file_icon = $this->get_file_icon_class( $item['file_type'] );
		$file_name = sprintf( '<div style="display: flex; gap: 6px;"><div class="dashicons %s"></div> <div>%s</div></div>', $file_icon, ! empty( $item['file_display_name'] ) ? $item['file_display_name'] : $item['file_name'] );

		return sprintf(
			'<div class="file-row" id="file-row-%d">
				<div class="file-info">%s %s</div>
			</div>',
			$item['id'],
			$file_name,
			$this->row_actions( $actions )
		);
	}

	public function get_edit_form_html( $file_id, $item ) {
		ob_start();
		?>
		<div id="edit-modal-<?php echo esc_attr( $file_id ); ?>" class="edit-modal" style="display:none;">
			<div class="edit-modal-content">
				<span class="close">&times;</span>
				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'edit_file_' . $file_id, 'edit_file_nonce' ); ?>
					<input type="hidden" name="file_id" value="<?php echo esc_attr( $file_id ); ?>">
					<table class="form-table">
						<tr>
							<th scope="row"><label for="file_display_name">File Display Name</label></th>
							<td>
								<input type="text" name="file_display_name" id="file_display_name"
									value="<?php echo esc_attr( $item['file_display_name'] ); ?>" class="regular-text">
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="file_name">File Name</label></th>
							<td>
								<p id="file_name" class="regular-text">
									<?php echo esc_attr( $item['file_name'] ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="file_description">Description</label></th>
							<td>
								<textarea name="file_description" id="file_description"
									value="<?php echo esc_textarea( $item['file_description'] ); ?>"
									class="regular-text"><?php echo esc_textarea( $item['file_description'] ); ?></textarea>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="md5_hash">MD5 Hash</label></th>
							<td>
								<p id="md5_hash" class="regular-text" style="word-break: break-all;">
									<?php echo esc_attr( $item['file_hash_md5'] ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="sha256_hash">SHA256 Hash</label></th>
							<td>
								<p id="sha256_hash" class="regular-text" style="word-break: break-all;">
									<?php echo esc_attr( $item['file_hash_sha256'] ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="file_url">File URL</label></th>
							<td>
								<p id="file_url" class="regular-text">
									<?php echo esc_attr( $item['file_url'] ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="file_category">File Category</label></th>
							<td>
								<select name="file_category_id" id="file_category">
									<option value="">None</option>
									<?php $this->display_category_options( $this->get_categories(), $item['file_category_id'] ); ?>
								</select>
							</td>
						</tr>
						<?php if ( ! get_option( 'fvm_auto_increment_version', 1 ) ) : ?>
							<tr>
								<th scope="row"><label for="file_version">Version</label></th>
								<td>
									<input type="number" step="0.1" min="0" name="file_version" id="file_version"
										value="<?php echo esc_attr( $item['file_version'] ); ?>" class="regular-text">
								</td>
							</tr>
						<?php endif; ?>
						<tr>
							<th scope="row"><label for="new_file">Replace File</label></th>
							<td><input type="file" name="new_file" id="new_file"></td>
						</tr>
					</table>
					<p class="submit">
						<input type="submit" name="update_file" id="update_file" class="button button-primary"
							value="Update File">
						<button type="button" class="button cancel-edit">Cancel</button>
					</p>
				</form>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private function get_categories() {
		global $wpdb;
		$cat_table_name = $wpdb->prefix . Constants::CAT_TABLE_NAME;
		$categories = $wpdb->get_results( "SELECT id, cat_name, cat_parent_id FROM $cat_table_name ORDER BY cat_parent_id ASC, cat_name ASC" );
		return $this->organize_categories_hierarchically( $categories );
	}

	private function organize_categories_hierarchically( $categories, $parent_id = 0 ) {
		$organized = [];
		foreach ( $categories as $category ) {
			if ( $category->cat_parent_id == $parent_id ) {
				$category->children = $this->organize_categories_hierarchically( $categories, $category->id );
				$organized[] = $category;
			}
		}
		return $organized;
	}

	private function display_category_options( $categories, $selected_id, $depth = 0 ) {
		foreach ( $categories as $category ) {
			$padding = str_repeat( '&nbsp;', $depth * 3 );
			$selected = ( $selected_id == $category->id ) ? 'selected' : '';
			echo "<option value='" . esc_attr( $category->id ) . "' $selected>" . $padding . esc_html( $category->cat_name ) . "</option>";
			if ( ! empty( $category->children ) ) {
				$this->display_category_options( $category->children, $selected_id, $depth + 1 );
			}
		}
	}

	private function format_file_size( $size_in_bytes ) {
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
		$size = $size_in_bytes;
		$unit_index = 0;

		while ( $size >= 1024 && $unit_index < count( $units ) - 1 ) {
			$size /= 1024;
			$unit_index++;
		}

		return round( $size, 2 ) . ' ' . $units[ $unit_index ];
	}

	private function get_file_icon_class( $file_type ) {
		$icon_map = [ 
			'jpg' => 'dashicons-format-image',
			'png' => 'dashicons-format-image',
			'webp' => 'dashicons-format-image',
			'svg' => 'dashicons-format-image',
			'gif' => 'dashicons-format-image',
			'mp3' => 'dashicons-format-audio',
			'mp4' => 'dashicons-format-video',
			'pdf' => 'dashicons-pdf',
			'text' => 'dashicons-text',
			'doc' => 'dashicons-media-document',
			'docx' => 'dashicons-media-document',
			'xls' => 'dashicons-media-spreadsheet',
			'xlsx' => 'dashicons-media-spreadsheet',
			'zip' => 'dashicons-media-archive',
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
}