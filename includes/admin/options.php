<?php
/**
 * Integrates the plugin’s options on the wp-admin dashboard.
 *
 * @package GdprCache
 */

namespace GdprCache;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;


// ----------------------------------------------------------------------------

add_action( 'admin_menu', __NAMESPACE__ . '\register_admin_menu' );

// ----------------------------------------------------------------------------


/**
 * Registers a new admin menu for our plugin.
 *
 * @since 1.0.0
 * @return void
 */
function register_admin_menu() {
	add_management_page(
		__( 'GDPR Cache Options', 'gdpr-cache' ),
		__( 'GDPR Cache', 'gdpr-cache' ),
		'manage_options',
		'gdpr-cache',
		__NAMESPACE__ . '\render_admin_page'
	);
}


/**
 * Renders the admin option-page of our plugin.
 *
 * @since 1.0.0
 * @return void
 */
function render_admin_page() {
	require GDPR_CACHE_PATH . 'templates/admin-options.php';
}
