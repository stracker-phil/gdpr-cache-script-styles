<?php
/**
 * Handles admin actions (usually triggered from the admin page).
 *
 * @package GdprCache
 */

namespace GdprCache;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;


// ----------------------------------------------------------------------------

add_action( 'admin_init', __NAMESPACE__ . '\process_actions' );

add_action( 'gdpr_cache_do_refresh', __NAMESPACE__ . '\action_refresh_cache' );

add_action( 'gdpr_cache_do_purge', __NAMESPACE__ . '\action_purge_cache' );

register_activation_hook( GDPR_CACHE_PLUGIN_FILE, __NAMESPACE__ . '\action_activate' );

register_deactivation_hook( GDPR_CACHE_PLUGIN_FILE, __NAMESPACE__ . '\action_deactivate' );

// ----------------------------------------------------------------------------


/**
 * Process admin actions during the admin_init hook.
 *
 * @since 1.0.0
 * @return void
 */
function process_actions() {
	if ( empty( $_REQUEST['action'] ) || empty( $_REQUEST['_wpnonce'] ) ) {
		return;
	}

	$action = sanitize_key( wp_unslash( $_REQUEST['action'] ) );

	if ( 0 !== strpos( $action, 'gdpr-cache-' ) ) {
		return;
	}

	// Remove the "gdpr-cache-" prefix from the action.
	$gdpr_action = substr( $action, 11 );

	if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_REQUEST['_wpnonce'] ) ), $gdpr_action ) ) {
		return;
	}

	/**
	 * Fire a custom action that contains the GDPR cache action, for example
	 * the action 'gdpr-cache-flush' fires the action 'gdpr_cache_do_flush'.
	 *
	 * @since 1.0.0
	 */
	do_action( "gdpr_cache_do_$gdpr_action" );
}


/**
 * Initiates a cache-refresh and display a success message on the admin page.
 *
 * @since 1.0.0
 * @return void
 */
function action_refresh_cache() {
	if ( ! current_user_can( GDPR_CACHE_CAPABILITY ) ) {
		return;
	}

	flush_cache( true );

	$redirect_to = add_query_arg( [
		'update'   => 'refreshed',
		'_wpnonce' => wp_create_nonce( 'gdpr-cache' ),
	] );

	wp_safe_redirect( $redirect_to );

	exit;
}


/**
 * Purges the cache and display a success message on the admin page.
 *
 * @since 1.0.1
 * @return void
 */
function action_purge_cache() {
	if ( ! current_user_can( GDPR_CACHE_CAPABILITY ) ) {
		return;
	}

	flush_cache();

	$redirect_to = add_query_arg( [
		'update'   => 'purged',
		'_wpnonce' => wp_create_nonce( 'gdpr-cache' ),
	] );

	wp_safe_redirect( $redirect_to );

	exit;
}


/**
 * Activation hook to set up the plugin.
 *
 * @since 1.0.4
 * @return void
 */
function action_activate() {
	// Set up the cron schedule.
	enable_cron();

	// Start scanning the front end.
	spawn_scanner();
}


/**
 * Deactivation hook to clean up the cache.
 *
 * @since 1.0.0
 * @return void
 */
function action_deactivate() {
	// Disable cron schedules.
	disable_cron();

	// Remove all plugin data from DB and uploads-folder.
	flush_cache();
}

	disable_cron();
}
