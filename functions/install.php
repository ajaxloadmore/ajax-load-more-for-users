<?php
/**
 * A file containing activation hooks and notices.
 *
 * @package ALM_users
 */

/**
 * Display admin notice Users add-on is installed.
 */
function alm_users_extension_pro_admin_notice() {
	// Ajax Load More Notice.
	if ( get_transient( 'alm_users_extension_pro_admin_notice' ) ) {
		$message  = '<div class="error">';
		$message .= '<p>You must deactivate the Users add-on in Ajax Load More Pro or update the Pro add-on before installing the Users extension.</p>';
		$message .= '<p><a href="./plugins.php">Back to Plugins</a></p>';
		$message .= '</div>';
		echo wp_kses_post( $message );
		delete_transient( 'alm_users_extension_pro_admin_notice' );
		wp_die();
	}
}
add_action( 'admin_notices', 'alm_users_extension_pro_admin_notice' );
