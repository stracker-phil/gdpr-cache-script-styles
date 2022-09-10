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
	$hook = add_management_page(
		__( 'GDPR Cache Options', 'gdpr-cache' ),
		__( 'GDPR Cache', 'gdpr-cache' ),
		'manage_options',
		'gdpr-cache',
		__NAMESPACE__ . '\render_admin_page'
	);

	add_action( "admin_head-$hook", __NAMESPACE__ . '\show_admin_notices' );

	// Spawn background worker when admin opens the options page.
	add_action( "admin_head-$hook", __NAMESPACE__ . '\spawn_worker' );
}


/**
 * Displays a success notice on the admin page after an action was performed.
 *
 * @since 1.0.0
 * @return void
 */
function show_admin_notices() {
	if ( empty( $_GET['update'] ) ) {
		return;
	}
	$notice = sanitize_key( wp_unslash( $_GET['update'] ) );

	if ( empty( $notice ) ) {
		return;
	}

	if ( 'flushed' === $notice ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\admin_notice_flushed' );
	}
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


/**
 * Outputs an admin notice: Cache flushed.
 *
 * @since 1.0.0
 * @return void
 */
function admin_notice_flushed() {
	printf(
		'<div class="notice-%s notice is-dismissible"><p>%s</p></div>',
		'success',
		esc_html__( 'Cache flushed', 'gdpr-cache' )
	);
}
