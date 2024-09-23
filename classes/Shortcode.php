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
				"SELECT * FROM $this->file_table_name WHERE id IN ($placeholders) AND file_offline != 1",
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
				WHERE r.category_id IN ($placeholders) AND f.file_offline != 1",
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

		if ( $atts['tpl'] === 'toggle' ) {
			return $this->toggle( $files, $atts );
		}

		if ( ! empty( $files ) ) {
			if ( $atts['tpl'] === 'thumbnail-grid-btns' || $atts['tpl'] === 'grid' ) {
				return $this->grid( $files );
			} elseif ( $atts['tpl'] === 'table' ) {
				return $this->table( $files, $atts );
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
		$max_depth = isset( $atts['max_depth'] ) ? intval( $atts['max_depth'] ) : 5;

		if ( $category_id === 0 ) {
			return '<p>Invalid category ID.</p>';
		}

		$categories = $this->get_category_hierarchy( $category_id, $max_depth );
		$direct_files = $this->get_direct_files( $category_id );

		if ( empty( $categories ) && empty( $direct_files ) ) {
			return '<p>No files available.</p>';
		}

		ob_start();
		?>
		<?php if ( ! empty( $title ) ) : ?>
			<h2 class="fvm-toggle-title"><?php echo esc_html( $title ); ?></h2>
		<?php endif; ?>
		<ul id="fvm-toggle-<?php echo $category_id; ?>" class="fvm-toggle-container">
			<?php echo $this->render_category_toggle( $categories, $direct_files, 0, $category_id ); ?>
		</ul>

		<script>
			document.addEventListener("DOMContentLoaded", function () {
				const toggleContainer = document.getElementById('fvm-toggle-<?php echo $category_id; ?>');
				toggleContainer.addEventListener('click', function (e) {
					const toggle = e.target.closest('.fvm-toggle-category-title');
					if (toggle) {
						const content = toggle.nextElementSibling;
						const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
						toggle.setAttribute('aria-expanded', !isExpanded);
						content.setAttribute('aria-hidden', isExpanded);
						content.classList.toggle('active');
						content.style.display = isExpanded ? 'none' : 'block';
						toggle.textContent = toggle.textContent.replace(/^[+-]/, isExpanded ? '+' : '-');
					}
				});
			});
		</script>
		<?php
		return ob_get_clean();
	}

	private function get_category_hierarchy( $category_id, $max_depth = 5 ) {
		$query = $this->wpdb->prepare( "
		WITH RECURSIVE category_tree AS (
			SELECT c.*, 0 AS level
			FROM {$this->cat_table_name} c
			WHERE c.cat_parent_id = %d
			
			UNION ALL
			
			SELECT c.*, ct.level + 1
			FROM {$this->cat_table_name} c
			JOIN category_tree ct ON c.cat_parent_id = ct.id
			WHERE ct.level < %d
		)
		SELECT ct.*, f.id AS file_id, f.file_name, f.file_display_name, f.file_url, f.file_size, f.file_type
		FROM category_tree ct
		LEFT JOIN {$this->rel_table_name} r ON ct.id = r.category_id
		LEFT JOIN {$this->file_table_name} f ON r.file_id = f.id AND f.file_offline != 1
		ORDER BY ct.level, ct.cat_name
		LIMIT 1000
		", $category_id, $max_depth );

		return $this->wpdb->get_results( $query );
	}

	private function get_direct_files( $category_id ) {
		return $this->wpdb->get_results( $this->wpdb->prepare( "
			SELECT f.*
			FROM {$this->rel_table_name} r
			JOIN {$this->file_table_name} f ON r.file_id = f.id
			WHERE r.category_id = %d AND f.file_offline != 1
			ORDER BY f.file_name
		", $category_id ) );
	}

	private function render_category_toggle( $categories, $direct_files, $level = 0, $parent_id = 0 ) {
		$output = '';
		$rendered_categories = [];

		foreach ( $categories as $category ) {
			if ( $category->cat_parent_id == $parent_id && ! in_array( $category->id, $rendered_categories ) ) {
				$rendered_categories[] = $category->id;
				if ( $category->cat_exclude_browser != 1 ) {
					$output .= $this->render_category( $category, $categories, $level );
				}
			}
		}

		if ( $level == 0 ) {
			foreach ( $direct_files as $file ) {
				$output .= $this->render_file_item( $file, $level );
			}
		}

		return $output;
	}

	private function render_category( $category, $all_categories, $level ) {
		$subcategories = $this->render_category_toggle( $all_categories, [], $level + 1, $category->id );
		$category_files = array_filter( $all_categories, function ($item) use ($category) {
			return $item->id == $category->id && $item->file_id;
		} );

		$files_output = '';
		foreach ( $category_files as $file ) {
			$files_output .= $this->render_file_item( $file, $level + 1 );
		}

		return sprintf(
			'<li id="fvm-category-%1$d" class="fvm-toggle-category" data-level="%2$d">
				<span class="fvm-toggle-category-title" aria-expanded="false">
					+ %3$s
				</span>
				<ul class="fvm-toggle-content" style="display: none;" aria-hidden="true">
					%4$s
					%5$s
				</ul>
			</li>',
			$category->id,
			$level,
			esc_html( $category->cat_name ),
			$subcategories,
			$files_output
		);
	}

	private function render_file_item( $file, $level ) {
		$file_name = ! empty( $file->file_display_name ) ? $file->file_display_name : $file->file_name;
		return sprintf(
			'<li id="fvm-file-%1$d" class="fvm-toggle-file" data-level="%2$d">
				<span>
					<a href="%3$s" title="%4$s" target="_blank" rel="noopener noreferrer">%4$s</a>
					<span class="fvm-file-item-meta">
						%5$s
						<span>(%6$s)</span>
					</span>
				</span>
			</li>',
			$file->id,
			$level,
			esc_url( $file->file_url ),
			esc_html( $file_name ),
			$file->file_type ? '<span>' . esc_html( $file->file_type ) . '</span>' : '',
			esc_html( size_format( $file->file_size ) )
		);
	}
}