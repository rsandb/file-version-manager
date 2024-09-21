<?php
namespace FVM\FileVersionManager;

class Shortcode {
	private $wpdb;
	private $file_table_name;
	private $cat_table_name;
	private $rel_table_name;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
		$this->file_table_name = $wpdb->prefix . Constants::FILE_TABLE_NAME;
		$this->cat_table_name = $wpdb->prefix . Constants::CAT_TABLE_NAME;
		$this->rel_table_name = $wpdb->prefix . Constants::REL_TABLE_NAME;
	}

	public function init() {
		add_shortcode( 'fvm', [ $this, 'shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'custom_shortcode_scripts' ] );
	}

	function custom_shortcode_scripts() {
		global $post;
		if ( isset( $post ) && is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'fvm' ) ) {
			wp_enqueue_style( 'fvm-shortcode', plugin_dir_url( __FILE__ ) . '../css/shortcode.css', array(), '1.0.0', 'all' );
		}
	}

	/**
	 * Shortcode handler function
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'tag' => '',
			'id' => 0,
			'tpl' => '',
			'title' => false,
		), $atts, 'fvm' );

		if ( $atts['tag'] === 'file' || empty( $atts['tag'] ) ) {
			$ids = array_map( 'intval', explode( ',', $atts['id'] ) );
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

			$files = $this->wpdb->get_results( $this->wpdb->prepare(
				"SELECT * FROM $this->file_table_name WHERE id IN ($placeholders)",
				$ids
			) );

			return $this->file( $files, $atts );

		} elseif ( $atts['tag'] === 'category' || $atts['tag'] === 'list' ) {
			$ids = array_map( 'intval', explode( ',', $atts['id'] ) );
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

			$files = $this->wpdb->get_results( $this->wpdb->prepare(
				"SELECT DISTINCT f.* 
                FROM $this->file_table_name f
                JOIN $this->rel_table_name r ON f.id = r.file_id
                WHERE r.category_id IN ($placeholders)",
				$ids
			) );

			return $this->category( $files, $atts );
		}
	}

	/**
	 * Handles the file shortcodes.
	 */
	private function file( $files, $atts ) {
		if ( ! empty( $files ) ) {
			if ( $atts['tpl'] === 'urlonly' || $atts['tpl'] === 'url' ) {
				return esc_url( $files[0]->file_url );
			} elseif ( $atts['tpl'] === 'table' ) {
				return $this->table( $files, $atts );
			} else {
				if ( count( $files ) === 1 ) {
					$file = $files[0];
					ob_start();
					?>
					<a href="<?php echo esc_url( $file->file_url ); ?>" target="_blank">
						<?php echo esc_html( ! empty( $file->file_display_name ) ? $file->file_display_name : $file->file_name ); ?>
					</a>
					<?php if ( $file->file_type ) : ?>
						<span class="fvm-item-size uppercase"> <?php echo esc_html( $file->file_type ); ?></span>
					<?php endif; ?>
					<span class="fvm-item-size"> (<?php echo esc_html( size_format( $file->file_size ) ); ?>)</span>
					<?php
					return ob_get_clean();
				} else {
					return $this->list( $files );
				}
			}
		} else {
			return '<p>File(s) no longer available.</p>';
		}
	}

	/**
	 * Handles the category shortcodes.
	 */
	private function category( $files, $atts ) {
		if ( ! empty( $files ) ) {
			if ( $atts['tpl'] === 'thumbnail-grid-btns' || $atts['tpl'] === 'grid' ) {
				return $this->grid( $files );
			} elseif ( $atts['tpl'] === 'table' ) {
				return $this->table( $files, $atts );
			} elseif ( $atts['tpl'] === 'toggle' ) {
				return $this->toggle( $files, $atts );
			} else {
				return $this->list( $files );
			}
		} else {
			return '<p>No files available.</p>';
		}
	}

	/**
	 * List
	 * -- Renders a list of files with their names, sizes, and types.
	 */
	private function list( $files ) {
		ob_start();
		?>
		<ul class="fvm-file-list">
			<?php foreach ( $files as $file ) : ?>
				<li>
					<?php
					$file_type = $file->file_type;
					$dashicon_class = '';

					switch ( $file_type ) {
						case 'jpeg':
						case 'jpg':
						case 'png':
						case 'gif':
						case 'webp':
						case 'svg+xml':
							$dashicon_class = 'dashicons-format-image';
							break;
						case 'pdf':
							$dashicon_class = 'dashicons-media-document';
							break;
						case 'zip':
							$dashicon_class = 'dashicons-archive';
							break;
						case 'txt':
							$dashicon_class = 'dashicons-media-text';
							break;
						default:
							$dashicon_class = 'dashicons-media-default';
							break;
					}
					?>
					<span class="dashicons <?php echo esc_attr( $dashicon_class ); ?>"></span>
					<a href="<?php echo esc_url( $file->file_url ); ?>" target="_blank" rel="noopener noreferrer">
						<?php echo esc_html( ! empty( $file->file_display_name ) ? $file->file_display_name : $file->file_name ); ?>
					</a>
					<?php if ( $file->file_type ) : ?>
						<span class="fvm-item-size uppercase"> <?php echo esc_html( $file->file_type ); ?></span>
					<?php endif; ?>
					<span class="fvm-item-size"> (<?php echo esc_html( size_format( $file->file_size ) ); ?>)</span>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
		return ob_get_clean();
	}

	/**
	 * Grid
	 * -- Renders a grid of files with their names, sizes, and types.
	 */
	private function grid( $files ) {
		ob_start();
		?>
		<div class="fvm-grid">
			<?php foreach ( $files as $file ) : ?>
				<div class="fvm-grid-item">
					<?php
					$file_type = $file->file_type;
					$dashicon_class = '';

					switch ( $file_type ) {
						case 'image/jpeg':
						case 'image/png':
						case 'image/gif':
						case 'image/webp':
						case 'image/svg+xml':
							$dashicon_class = 'dashicons-format-image';
							break;
						case 'application/pdf':
							$dashicon_class = 'dashicons-media-document';
							break;
						case 'application/zip':
							$dashicon_class = 'dashicons-archive';
							break;
						case 'text/plain':
							$dashicon_class = 'dashicons-media-text';
							break;
						default:
							$dashicon_class = 'dashicons-media-default';
							break;
					}
					?>
					<div class="fvm-grid-item-content">
						<div class="fvm-grid-item-icon">
							<span class="dashicons <?php echo esc_attr( $dashicon_class ); ?>"></span>
						</div>
						<p class="fvm-grid-item-title">
							<?php echo esc_html( ! empty( $file->file_display_name ) ? $file->file_display_name : $file->file_name ); ?>
						</p>
						<div class="fvm-grid-item-meta">
							<?php if ( $file->file_type ) : ?>
								<span class="fvm-item-size uppercase"><?php echo esc_html( $file->file_type ); ?> </span>
							<?php endif; ?>
							<span class="fvm-item-size">(<?php echo esc_html( size_format( $file->file_size ) ); ?>)</span>
						</div>
					</div>
					<a class="fvm-grid-item-btn" href="<?php echo esc_url( $file->file_url ); ?>" target="_blank"
						rel="noopener noreferrer">View/Download</a>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Table
	 * -- Renders a table of files with their names, sizes, and types.
	 */
	private function table( $files, $atts ) {
		$files = is_array( $files ) ? $files : array( $files );

		$has_description = array_reduce( $files, function ($carry, $file) {
			return $carry || ! empty( $file->file_description );
		}, false );

		// Fetch categories for all files in a single query
		$file_ids = array_column( $files, 'id' );
		$placeholders = implode( ',', array_fill( 0, count( $file_ids ), '%d' ) );
		$categories = $this->wpdb->get_results( $this->wpdb->prepare(
			"SELECT r.file_id, c.cat_name 
            FROM $this->rel_table_name r
            JOIN $this->cat_table_name c ON r.category_id = c.id
            WHERE r.file_id IN ($placeholders)",
			$file_ids
		) );

		// Organize categories by file_id
		$file_categories = [];
		foreach ( $categories as $category ) {
			$file_categories[ $category->file_id ][] = $category->cat_name;
		}

		ob_start();
		?>
		<table class="fvm-file-table">
			<thead>
				<tr>
					<th><?php echo $atts['title'] ? esc_html( $atts['title'] ) : 'File Name'; ?></th>
					<?php if ( $has_description ) : ?>
						<th>Description</th>
					<?php endif; ?>
					<th>Version</th>
					<th>File Size</th>
					<th>Categories</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $files as $file ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( $file->file_url ); ?>" target="_blank"
								rel="noopener noreferrer"><?php echo esc_html( ! empty( $file->file_display_name ) ? $file->file_display_name : $file->file_name ); ?></a>
						</td>
						<?php if ( $has_description ) : ?>
							<td><?php echo esc_html( $file->file_description ); ?></td>
						<?php endif; ?>
						<td><?php echo esc_html( $file->file_version ); ?></td>
						<td><?php echo esc_html( size_format( $file->file_size, 1 ) ); ?></td>
						<td><?php echo esc_html( implode( ', ', $file_categories[ $file->id ] ?? [] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		return ob_get_clean();
	}

	/**
	 * Toggle
	 * -- Renders a toggle of categories with it's associated files
	 */
	public function toggle( $files, $atts ) {
		$category_id = intval( $atts['id'] );
		$title = isset( $atts['title'] ) ? sanitize_text_field( $atts['title'] ) : '';

		// Fetch categories and files
		$categories = $this->get_category_hierarchy( $category_id );
		$direct_files = $this->get_direct_files( $category_id );

		// Render the toggle
		ob_start();
		if ( ! empty( $title ) ) {
			echo '<h2 class="fvm-toggle-title">' . esc_html( $title ) . '</h2>';
		}
		echo '<div class="fvm-toggle-container">';
		echo $this->render_category_toggle( $categories, $direct_files, 0, $category_id );
		echo '</div>';

		// Add JavaScript for toggle functionality
		echo '<script>
			document.addEventListener("DOMContentLoaded", function() {
				var toggles = document.querySelectorAll(".fvm-toggle-header");
				toggles.forEach(function(toggle) {
					toggle.addEventListener("click", function() {
						this.classList.toggle("active");
						var content = this.nextElementSibling;
						if (content.style.display === "block") {
							content.style.display = "none";
						} else {
							content.style.display = "block";
						}
					});
				});
			});
		</script>';

		return ob_get_clean();
	}

	private function get_category_hierarchy( $category_id ) {
		return $this->wpdb->get_results( $this->wpdb->prepare( "
			WITH RECURSIVE category_tree AS (
				SELECT c.*, 0 AS level
				FROM {$this->cat_table_name} c
				WHERE c.cat_parent_id = %d
				
				UNION ALL
				
				SELECT c.*, ct.level + 1
				FROM {$this->cat_table_name} c
				JOIN category_tree ct ON c.cat_parent_id = ct.id
			)
			SELECT ct.*, f.id AS file_id, f.file_name, f.file_display_name, f.file_url, f.file_size, f.file_type
			FROM category_tree ct
			LEFT JOIN {$this->rel_table_name} r ON ct.id = r.category_id
			LEFT JOIN {$this->file_table_name} f ON r.file_id = f.id
			ORDER BY ct.level, ct.cat_name
		", $category_id ) );
	}

	private function get_direct_files( $category_id ) {
		return $this->wpdb->get_results( $this->wpdb->prepare( "
			SELECT f.*
			FROM {$this->rel_table_name} r
			JOIN {$this->file_table_name} f ON r.file_id = f.id
			WHERE r.category_id = %d
			ORDER BY f.file_name
		", $category_id ) );
	}

	private function render_category_toggle( $categories, $direct_files, $level = 0, $parent_id = 0 ) {
		$has_content = false;
		$output = '';
		$output .= '<ul class="fvm-toggle-subcategory level-' . $level . '">';

		foreach ( $categories as $category ) {
			if ( $category->cat_parent_id == $parent_id ) {
				$has_content = true;
				$output .= '<li><div class="fvm-toggle-header">+ ' . esc_html( $category->cat_name ) . '</div><div class="fvm-toggle-content" style="display: none;"><ul>';

				// Recursively render subcategories
				$subcategory_content = $this->render_category_toggle( $categories, [], $level + 1, $category->id );
				if ( $subcategory_content !== '' ) {
					$output .= $subcategory_content;
				}

				// Render files for this category
				$category_files = array_filter( $categories, function ($item) use ($category) {
					return $item->id == $category->id && $item->file_id;
				} );
				foreach ( $category_files as $file ) {
					$output .= $this->render_file_item( $file );
				}

				$output .= '</ul></div></li>';
			}
		}

		// Render direct files only for the top-level call
		if ( $level == 0 ) {
			foreach ( $direct_files as $file ) {
				$has_content = true;
				$output .= $this->render_file_item( $file );
			}
		}

		$output .= '</ul>';

		return $has_content ? $output : '';
	}

	private function render_file_item( $file ) {
		ob_start();
		?>
		<li class="fvm-file-item">
			<a href="<?php echo esc_url( $file->file_url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php echo esc_html( ! empty( $file->file_display_name ) ? $file->file_display_name : $file->file_name ); ?>
			</a>
			<?php if ( $file->file_type ) : ?>
				<span class="fvm-item-size uppercase"><?php echo esc_html( $file->file_type ); ?></span>
			<?php endif; ?>
			<span class="fvm-item-size">(<?php echo esc_html( size_format( $file->file_size ) ); ?>)</span>
		</li>
		<?php
		return ob_get_clean();
	}
}