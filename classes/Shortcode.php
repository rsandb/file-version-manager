<?php
namespace LVAI\FileVersionManager;

class Shortcode {
	private $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function init() {
		add_shortcode( 'fvm', [ $this, 'shortcode' ] );
	}

	public function shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'id' => 0,
		), $atts, 'fvm' );

		$table_name = $this->wpdb->prefix . Constants::TABLE_NAME;
		$file = $this->wpdb->get_row( $this->wpdb->prepare(
			"SELECT * FROM $table_name WHERE id = %d",
			$atts['id']
		) );

		if ( $file ) {
			return '<a href="' . esc_url( home_url( 'download/' . $file->file_name ) ) . '" target="_blank">' . esc_html( $file->file_name ) . '</a>';
		} else {
			return '<p>File is no longer available.</p>';
		}
	}
}