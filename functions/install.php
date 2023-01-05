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
	$slug   = 'ajax-load-more';
	$plugin = $slug . '-for-users';
	// Ajax Load More Notice.
	if ( get_transient( 'alm_users_extension_pro_admin_notice' ) ) {
		$install_url = get_admin_url() . '/update.php?action=install-plugin&plugin=' . $slug . '&_wpnonce=' . wp_create_nonce( 'install-plugin_' . $slug );
		$message     = '<div class="error">';
		$message    .= '<p>You must deactivate the Users add-on in Ajax Load More Pro or update the Pro add-on before installing the Users extension.</p>';
		$message    .= '<p><a href="./plugins.php">Back to Plugins</a></p>';
		$message    .= '</div>';
		echo wp_kses_post( $message );
		delete_transient( 'alm_users_extension_pro_admin_notice' );
		wp_die();
	}
}
add_action( 'admin_notices', 'alm_users_extension_pro_admin_notice' );

/**
 * Display admin notice if plugin does not meet the requirements.
 */
function alm_users_extension_admin_notice() {
	$slug   = 'ajax-load-more';
	$plugin = $slug . '-for-users';
	// Ajax Load More Notice.
	if ( get_transient( 'alm_users_extension_admin_notice' ) ) {
		$install_url = get_admin_url() . '/update.php?action=install-plugin&plugin=' . $slug . '&_wpnonce=' . wp_create_nonce( 'install-plugin_' . $slug );
		$message     = '<div class="error">';
		$message    .= '<p>You must install and activate the core Ajax Load More plugin before using the Ajax Load More Users extension.</p>';
		$message    .= '<p>' . sprintf( '<a href="%s" class="button-primary">%s</a>', $install_url, 'Install Ajax Load More Now' ) . '</p>';
		$message    .= '</div>';
		echo wp_kses_post( $message );
		delete_transient( 'alm_users_extension_admin_notice' );
	}
}
add_action( 'admin_notices', 'alm_users_extension_admin_notice' );
