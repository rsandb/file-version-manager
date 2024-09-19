<?php
namespace FVM\FileVersionManager;

class Shortcode {
	private $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
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

		$table_name = $this->wpdb->prefix . Constants::FILE_TABLE_NAME;

		if ( $atts['tag'] === 'file' || empty( $atts['tag'] ) ) {
			$ids = array_map( 'intval', explode( ',', $atts['id'] ) );
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

			$files = $this->wpdb->get_results( $this->wpdb->prepare(
				"SELECT * FROM $table_name WHERE id IN ($placeholders)",
				$ids
			) );

			return $this->file( $files, $atts );

		} elseif ( $atts['tag'] === 'category' || $atts['tag'] === 'list' ) {
			$ids = array_map( 'intval', explode( ',', $atts['id'] ) );
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

			$files = $this->wpdb->get_results( $this->wpdb->prepare(
				"SELECT * FROM $table_name WHERE file_category_id IN ($placeholders)",
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
			if ( $atts['tpl'] === 'thumbnail-grid-btns' ) {
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
	 * Renders a list of files with their names, sizes, and types.
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
	 * Renders a grid of files with their names, sizes, and types.
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
	 * Renders a table of files with their names, sizes, and types.
	 */
	private function table( $files, $atts ) {
		$files = is_array( $files ) ? $files : array( $files );

		$has_description = array_reduce( $files, function ($carry, $file) {
			return $carry || ! empty( $file->file_description );
		}, false );

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
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		return ob_get_clean();
	}
}