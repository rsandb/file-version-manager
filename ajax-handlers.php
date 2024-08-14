<?php
// use LVAI\FileVersionManager\FileManager;

// add_action( 'wp_ajax_update_file', 'lvai_update_file' );

// function lvai_update_file() {
// 	error_log( 'AJAX update_file action called' );

// 	if ( ! current_user_can( 'manage_options' ) ) {
// 		wp_send_json_error( 'Insufficient permissions' );
// 		return;
// 	}

// 	if ( ! isset( $_POST['file_id'] ) || ! isset( $_POST['edit_file_nonce'] ) ) {
// 		wp_send_json_error( 'Missing required fields' );
// 		return;
// 	}

// 	$file_id = intval( $_POST['file_id'] );

// 	if ( ! wp_verify_nonce( $_POST['edit_file_nonce'], 'edit_file_' . $file_id ) ) {
// 		wp_send_json_error( 'Invalid nonce' );
// 		return;
// 	}

// 	$file = isset( $_FILES['new_file'] ) ? $_FILES['new_file'] : null;
// 	$file_name = isset( $_POST['file_name'] ) ? sanitize_text_field( $_POST['file_name'] ) : '';
// 	$file_type = isset( $_POST['file_type'] ) ? sanitize_text_field( $_POST['file_type'] ) : '';
// 	$version = isset( $_POST['version'] ) ? sanitize_text_field( $_POST['version'] ) : '';

// 	error_log( "Updating file ID: $file_id, Version: $version" );

// 	$file_manager = new FileManager( $GLOBALS['wpdb'] );
// 	$update_result = $file_manager->update_file( $file_id, $file, $file_name, $file_type, $version );

// 	if ( $update_result ) {
// 		error_log( "File update successful for file ID: $file_id" );
// 		wp_send_json_success( 'File updated successfully' );
// 	} else {
// 		error_log( "File update failed for file ID: $file_id" );
// 		wp_send_json_error( 'Error updating file' );
// 	}
// }