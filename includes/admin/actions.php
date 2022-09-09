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

add_action( 'gdpr_cache_do_flush', __NAMESPACE__ . '\action_flush_cache' );

// ----------------------------------------------------------------------------


/**
 * Process admin actions during the admin_init hook.
 *
 * @since 1.0.0
 * @return void
 */
function process_actions() {
	if ( empty( $_POST['action'] ) || empty( $_POST['_wpnonce'] ) ) {
		return;
	}

	$action = wp_unslash( sanitize_key( $_POST['action'] ) );

	if ( 0 !== strpos( $action, 'gdpr-cache-' ) ) {
		return;
	}

	// Remove the "gdpr-cache-" prefix from the action.
	$gdpr_action = substr( $action, 11 );

	if ( ! wp_verify_nonce( $_POST['_wpnonce'], $gdpr_action ) ) {
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
 * Renders the admin option-page of our plugin.
 *
 * @since 1.0.0
 * @return void
 */
function action_flush_cache() {
	wp_die( 'TODO: Flush cache' );
}
