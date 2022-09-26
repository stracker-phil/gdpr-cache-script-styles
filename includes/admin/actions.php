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


/**
 * Attempts flushing the entire WP cache. Auto-detects most caching plugins
 * and hosting environments. This function flushes the object cache as well as
 * minified JS and CSS files.
 *
 * @since 1.0.5
 * @return void
 */
function flush_wp_caches() {
	/**
	 * Before any cache is flushed.
	 *
	 * @since 1.0.5
	 */
	do_action( 'gdpr_cache_before_flush_wp_caches' );

	try {
		// W3 Total Cache.
		if ( function_exists( 'w3tc_minify_flush' ) ) {
			w3tc_minify_flush();
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}
		if ( function_exists( 'w3tc_pgcache_flush' ) ) {
			w3tc_pgcache_flush();
		}

		// WP Super Cache.
		if ( function_exists( 'wp_cache_clean_cache' ) ) {
			if (
				empty( $GLOBALS['supercachedir'] )
				&& function_exists( 'get_supercache_dir' )
			) {
				$GLOBALS['supercachedir'] = get_supercache_dir();
			}
			wp_cache_clean_cache( $GLOBALS['file_prefix'] );
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}

		// WPMU DEV Hummingbird.
		if ( class_exists( 'WP_Hummingbird' ) ) {
			WP_Hummingbird::flush_cache( false, false );
		}

		// WP Rocket.
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}

		// WP Fastest Cache.
		if (
			isset( $GLOBALS['wp_fastest_cache'] )
			&& method_exists( $GLOBALS['wp_fastest_cache'], 'deleteCache' )
		) {
			$GLOBALS['wp_fastest_cache']->deleteCache( true );
		}

		// Litespeed Cache.
		do_action( 'litespeed_purge_all', 'Divimode' );

		// Comet Cache.
		if (
			class_exists( 'comet_cache' )
			&& is_callable( 'comet_cache::wipe' )
		) {
			comet_cache::wipe();
		}

		// Autoptimize.
		if (
			class_exists( 'autoptimizeCache' )
			&& is_callable( 'autoptimizeCache::clearall' )
		) {
			autoptimizeCache::clearall();
		}

		// Cache Enabler.
		if (
			class_exists( 'Cache_Enabler' )
			&& is_callable( 'Cache_Enabler', 'clear_total_cache' )
		) {
			Cache_Enabler::clear_total_cache();
		}

		// Cloudways Breeze.
		do_action( 'breeze_clear_all_cache' );
		do_action( 'breeze_clear_varnish' );

		// WP Optimize.
		if (
			class_exists( 'WP_Optimize' )
			&& defined( 'WPO_PLUGIN_MAIN_PATH' )
		) {
			include_once WPO_PLUGIN_MAIN_PATH . 'cache/class-cache-commands.php';

			if ( class_exists( 'WP_Optimize_Cache_Commands' ) ) {
				$wpo_cache_commands = new WP_Optimize_Cache_Commands;

				if ( is_callable( [ $wpo_cache_commands, 'purge_page_cache' ] ) ) {
					$wpo_cache_commands->purge_page_cache();
				}
			}
		}

		// Host: SG Optimizer.
		if ( isset( $GLOBALS['sg_cachepress_supercacher'] ) ) {
			$cache = $GLOBALS['sg_cachepress_supercacher'];

			if ( is_callable( [ $cache, 'purge_everything' ] ) ) {
				$cache->purge_everything();
			}
			if ( is_callable( [ $cache, 'delete_assets' ] ) ) {
				$cache->delete_assets();
			}
		}
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
		}
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
		}

		// Host: Pantheon Advanced Page Cache.
		if ( function_exists( 'pantheon_wp_clear_edge_all' ) ) {
			pantheon_wp_clear_edge_all();
		}

		// Host: GoDaddy. Mark all URLs to be purged.
		if ( class_exists( '\WPaaS\Cache' ) ) {
			if ( ! \WPaaS\Cache::has_ban() ) {
				remove_action( 'shutdown', [ '\WPaaS\Cache', 'purge' ], PHP_INT_MAX );
				add_action( 'shutdown', [ '\WPaaS\Cache', 'ban' ], PHP_INT_MAX );
			}
		}

		// Host: WPEngine.
		if ( class_exists( 'WpeCommon' ) ) {
			if ( is_callable( 'WpeCommon::purge_memcached' ) ) {
				WpeCommon::purge_memcached();
			}
			if ( is_callable( 'WpeCommon::clear_maxcdn_cache' ) ) {
				WpeCommon::clear_maxcdn_cache();
			}
			if ( is_callable( 'WpeCommon::purge_varnish_cache' ) ) {
				WpeCommon::purge_varnish_cache();
			}
		}

		// Host: Kinsta.
		if (
			class_exists( '\Kinsta\Cache' )
			&& isset( $GLOBALS['kinsta_cache'] )
			&& is_object( $GLOBALS['kinsta_cache'] )
			&& isset( $GLOBALS['kinsta_cache']->kinsta_cache_purge )
		) {
			$purger = $GLOBALS['kinsta_cache']->kinsta_cache_purge;
			if ( method_exists( $purger, 'purge_complete_caches' ) ) {
				$purger->purge_complete_caches();
			}
		}
	} catch ( Exception $exception ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( 'Divimode could not clear the website cache: ' . $exception->getMessage() );
	}

	// WordPress "native" cache.
	wp_cache_flush();

	/**
	 * Fires after all global caches were cleared. Plugins can use this hook if
	 * they need to reset some transients, option values, etc.
	 *
	 * @since 1.0.5
	 */
	do_action( 'gdpr_cache_after_flush_wp_caches' );
}
