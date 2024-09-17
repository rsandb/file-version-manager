<?php

/**
 * Redirect with message
 * 
 * @param string $status
 * @param string $message
 * @return void
 */
function fvm_redirect_with_message( $page, $status, $message ) {
	$redirect_url = add_query_arg(
		[ 
			'page' => $page,
			'update' => $status,
			'message' => urlencode( $message ),
		],
		admin_url( 'admin.php' )
	);

	if ( ! headers_sent() ) {
		wp_redirect( $redirect_url );
		exit;
	} else {
		echo '<script type="text/javascript">';
		echo 'window.location.href="' . esc_js( $redirect_url ) . '";';
		echo '</script>';
		echo '<noscript>';
		echo '<meta http-equiv="refresh" content="0;url=' . esc_url( $redirect_url ) . '" />';
		echo '</noscript>';
		exit;
	}
}