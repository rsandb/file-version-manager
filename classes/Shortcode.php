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
			'tpl' => '',
		), $atts, 'fvm' );

		$table_name = $this->wpdb->prefix . Constants::TABLE_NAME;
		$file = $this->wpdb->get_row( $this->wpdb->prepare(
			"SELECT * FROM $table_name WHERE id = %d",
			$atts['id']
		) );

		if ( $file ) {
			if ( $atts['tpl'] === 'urlonly' ) {
				return esc_url( $file->file_url );
			} else {
				return '<a href="' . esc_url( $file->file_url ) . '" target="_blank">' . esc_html( $file->file_name ) . '</a>';
			}
		} else {
			if ( $atts['tpl'] === 'urlonly' ) {
				return '';
			} else {
				return '<p>File is no longer available.</p>';
			}
		}
	}
}