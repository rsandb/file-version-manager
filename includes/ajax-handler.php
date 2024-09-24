<?php

add_action( 'wp_ajax_get_file_data', 'fvm_get_file_data' );

function fvm_get_file_data() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized' );
	}

	check_ajax_referer( 'get_file_data', '_ajax_nonce' );

	$file_id = isset( $_GET['file_id'] ) ? intval( $_GET['file_id'] ) : 0;
	if ( ! $file_id ) {
		wp_send_json_error( 'Invalid file ID' );
	}

	$file_manager = new FVM\FileVersionManager\FileManager( $GLOBALS['wpdb'] );
	$file_data = $file_manager->get_file_data( $file_id );

	if ( $file_data ) {
		wp_send_json_success( $file_data );
	} else {
		wp_send_json_error( 'File not found' );
	}
}