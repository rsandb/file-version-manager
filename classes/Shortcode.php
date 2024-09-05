<?php
namespace LVAI\FileVersionManager;

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
		), $atts, 'fvm' );

		$table_name = $this->wpdb->prefix . Constants::FILE_TABLE_NAME;

		if ( $atts['tag'] === 'file' || empty( $atts['tag'] ) ) {

			$file = $this->wpdb->get_row( $this->wpdb->prepare(
				"SELECT * FROM $table_name WHERE id = %d",
				$atts['id']
			) );
			return $this->file( $file, $atts );

		} elseif ( $atts['tag'] === 'category' || $atts['tag'] === 'list' ) {

			$file = $this->wpdb->get_row( $this->wpdb->prepare(
				"SELECT * FROM $table_name WHERE file_category_id = %d",
				$atts['id']
			) );
			return $this->category( $file, $atts );
		}
	}

	/**
	 * Handles the file shortcodes.
	 */
	private function file( $file, $atts ) {
		if ( $file ) {
			if ( $atts['tpl'] === 'urlonly' || $atts['tpl'] === 'url' ) {
				return esc_url( $file->file_url );
			} else {
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
			}
		} else {
			return '<p>File is no longer available.</p>';
		}
	}

	/**
	 * Handles the category shortcodes.
	 */
	private function category( $file, $atts ) {
		if ( $file ) {
			if ( $atts['tpl'] === 'thumbnail-grid-btns' ) {
				return $this->grid( $file );
			} elseif ( $atts['tpl'] === 'table' ) {
				return $this->table( $file );
			} else {
				return $this->list( $file );
			}
		} else {
			return '<p>File is no longer available.</p>';
		}
	}

	/**
	 * Renders a list of files with their names, sizes, and types.
	 */
	private function list( $file ) {
		$files = $this->wpdb->get_results( $this->wpdb->prepare(
			"SELECT * FROM {$this->wpdb->prefix}" . Constants::FILE_TABLE_NAME . " WHERE file_category_id = %d",
			$file->file_category_id
		) );
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
	private function grid( $file ) {
		$files = $this->wpdb->get_results( $this->wpdb->prepare(
			"SELECT * FROM {$this->wpdb->prefix}" . Constants::FILE_TABLE_NAME . " WHERE file_category_id = %d",
			$file->file_category_id
		) );
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
	private function table( $file ) {
		$files = $this->wpdb->get_results( $this->wpdb->prepare(
			"SELECT * FROM {$this->wpdb->prefix}" . Constants::FILE_TABLE_NAME . " WHERE file_category_id = %d ORDER BY file_display_name ASC",
			$file->file_category_id
		) );

		ob_start();
		?>
		<table class="fvm-file-table">
			<thead>
				<tr>
					<th>File Name</th>
					<th>File Size</th>
					<th>File Type</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $files as $file ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( $file->file_url ); ?>" target="_blank"
								rel="noopener noreferrer"><?php echo esc_html( ! empty( $file->file_display_name ) ? $file->file_display_name : $file->file_name ); ?></a>
						</td>
						<td><?php echo esc_html( size_format( $file->file_size, 1 ) ); ?></td>
						<td><?php echo esc_html( $file->file_type ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		return ob_get_clean();
	}
}