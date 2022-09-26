<?php
/**
 * Background worker that refreshes the asset cache asynchronously.
 *
 * @package GdprCache
 */

namespace GdprCache;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;


// ----------------------------------------------------------------------------

add_action( 'gdpr_cache_scan_front', __NAMESPACE__ . '\run_scan_frontend' );
add_action( 'gdpr_cache_worker', __NAMESPACE__ . '\run_background_tasks' );
add_action( 'gdpr_cache_check_staleness', __NAMESPACE__ . '\run_staleness_checks' );

// ----------------------------------------------------------------------------


/**
 * Spawns a background process to process the GDPR cache worker.
 *
 * @since 1.0.0
 * @return void
 */
function spawn_worker() {
	if ( defined( 'GDPR_CACHE__WORKER_SPAWNED' ) ) {
		return;
	}

	if ( is_worker_busy() ) {
		// Background worker is currently active, we do not start it again
		// until it finishes or times out.
		return;
	}

	if ( ! has_worker_queue() ) {
		// The queue is empty. No need to spawn a background worker.
		return;
	}

	wp_schedule_single_event( time(), 'gdpr_cache_worker' );
	spawn_cron();

	define( 'GDPR_CACHE__WORKER_SPAWNED', true );
}


/**
 * Spawns a new cron-task that scans the front-end of the website for external
 * scripts.
 *
 * Though "scan" is not correct here:
 *
 * The worker loads the front end with a cookieless request and a random URL.
 * This simulates a guest visitor landing on a new page on your website.
 * While generating the HTML response for that front-end request, the plugin's
 * logic detects external scripts and starts to cache them if needed.
 *
 * @since 1.0.5
 * @return void
 */
function spawn_scanner() {
	wp_schedule_single_event( time(), 'gdpr_cache_scan_front' );
	spawn_cron();
}


/**
 * Adds a new cron task that checks for stale assets.
 *
 * @since 1.0.4
 * @return void
 */
function enable_cron() {
	$schedule = wp_get_scheduled_event( 'gdpr_cache_check_staleness' );
	if ( $schedule ) {
		return;
	}

	wp_schedule_event( time(), 'daily', 'gdpr_cache_check_staleness' );
}


/**
 * Disables the stale-asset-check cron task.
 *
 * @since 1.0.4
 * @return void
 */
function disable_cron() {
	wp_clear_scheduled_hook( 'gdpr_cache_check_staleness' );
}


/**
 * Enqueues an external asset for local caching.
 *
 * @since 1.0.0
 *
 * @param string $url The external URL to download and cache.
 *
 * @return string|false Either false, when the asset was enqueued, or the local
 * URL when the asset was processed instantly.
 */
function enqueue_asset( $url ) {
	if ( ! $url || false === strpos( $url, '//' ) ) {
		return false;
	}

	if ( defined( 'GDPR_CACHE__IS_BACKGROUND_WORKER' ) ) {
		// Instantly process the asset.
		return cache_file_locally( $url );
	} else {
		// Enqueue the asset for caching.
		$queue = get_worker_queue();

		if ( in_array( $url, $queue, true ) ) {
			return false;
		}

		$queue[] = $url;

		set_worker_queue( $queue );
	}

	return false;
}


/**
 * Checks, if the background worker is currently running.
 *
 * @since 1.0.0
 * @return bool True, if the background worker is spawned and running.
 */
function is_worker_busy() {
	// Check if the background worker is running
	$lock = (int) get_option( GDPR_CACHE_WORKER_LOCK );

	if ( ! $lock ) {
		return false;
	}

	/**
	 * Filters the duration for which the background tasks can stay idle before
	 * being considered "timed out"
	 *
	 * @since 1.0.0
	 */
	$timeout = apply_filters( 'gdpr_cache_worker_timeout', 300 );

	return $lock < time() - $timeout;
}


/**
 * Prevents background worker from spawning.
 *
 * @since 1.0.4
 * @return void
 */
function lock_worker() {
	update_option( GDPR_CACHE_WORKER_LOCK, time() );
}


/**
 * Allows spawning of a new background worker process.
 *
 * @since 1.0.4
 * @return void
 */
function unlock_worker() {
	delete_option( GDPR_CACHE_WORKER_LOCK );
}


/**
 * Runs a background task that loads the front-end of the website as a guest
 * visitor to prime the cache.
 *
 * @since 1.0.5
 * @return void
 */
function run_scan_frontend() {
	$scan_url = add_query_arg( [ 'gdpr-check' => microtime() ], home_url() );

	// Request the home-page with a "random" query parameter.
	wp_remote_get( $scan_url );
}


/**
 * Runs the background worker tasks; is called via a cron event.
 *
 * @since 1.0.0
 * @return void
 */
function run_background_tasks() {
	if ( is_worker_busy() ) {
		// Another background worker is currently active.
		return;
	}

	define( 'GDPR_CACHE__IS_BACKGROUND_WORKER', true );

	/**
	 * Action that fires before the background worker starts to process
	 * the enqueued assets.
	 *
	 * @since 1.0.0
	 */
	do_action( 'gdpr_cache_before_worker' );

	lock_worker();

	$queue = get_worker_queue();

	// Process the worker queue.
	while ( $queue ) {
		$item_url = array_shift( $queue );

		if ( ! is_string( $item_url ) || ! $item_url ) {
			continue;
		}
		if ( ! is_external_url( $item_url ) ) {
			continue;
		}

		cache_file_locally( $item_url );

		set_worker_queue( $queue );

		lock_worker();
	}

	unlock_worker();

	/**
	 * Action that fires after the background worker finished all enqueued
	 * tasks and cleaned up.
	 *
	 * @since 1.0.0
	 */
	do_action( 'gdpr_cache_after_worker' );
}


/**
 * When using the plugin for a longer period of time, it will cache many files.
 * It can happen, that some of those cached assets are not used on the website
 * anymore
 *
 * For example, when you switch to a different Google Font, the new font is
 * cached, but the old font is not removed from cache.
 *
 * This function reviews the "last-used" timestamps to determine, which assets
 * have become stale and should be removed from the website.
 *
 * A cron-job calls this function once per day.
 *
 * @since 1.0.4
 * @return void
 */
function run_staleness_checks() {
	if ( is_worker_busy() ) {
		// Background worker is busy. Try again in a few seconds.
		wp_schedule_single_event( time() + 15, 'gdpr_cache_check_staleness' );

		return;
	}

	/**
	 * Action that fires before the cron task starts to check for stale files.
	 *
	 * @since 1.0.4
	 */
	do_action( 'gdpr_cache_before_stale_check' );

	lock_worker();

	$fs    = get_filesystem();
	$cache = get_cached_data();
	$queue = get_worker_queue();

	foreach ( $cache as $url => $data ) {
		$stale_age = get_asset_staleness( $url );

		/**
		 * Determines if a file has become stale.
		 *
		 * By default, this is TRUE when the file was not served for 30 days.
		 * Once the file is stale, it's removed from the cache.
		 *
		 * @since 1.0.4
		 *
		 * @param bool   $is_stale Whether the file is considered to be stale.
		 * @param int    $age      How many hours ago the file was last served.
		 * @param string $url      The remote source of the file.
		 * @param array  $data     Details about the cache.
		 */
		$is_stale = apply_filters(
			'gdpr_cache_is_file_stale',
			$stale_age >= GDPR_CACHE_STALE_HOURS,
			$stale_age,
			$url,
			$data
		);

		if ( ! $is_stale ) {
			continue;
		}

		/**
		 * Fires right before a stale file is removed from the DB
		 *
		 * @since 1.0.4
		 *
		 * @param string $url  The remote source of the file.
		 * @param array  $data Details about the cache.
		 */
		do_action( 'gdpr_cache_remove_stale_file', $url, $data );

		// Remove the file from the cache.
		unset ( $cache[ $url ] );

		// De-queue eventual background tasks.
		unset ( $queue[ $url ] );

		// Remove links to any dependencies.
		set_dependencies( $url, null );

		// Delete the local file.
		$local_file = build_cache_file_path( $data['file'] );

		if ( $fs->exists( $local_file ) ) {
			$fs->delete( $local_file, false, 'f' );
		}
	}

	set_cached_data( $cache );
	set_worker_queue( $queue );

	unlock_worker();

	/**
	 * Action that fires after the cron task finished checking for stale files.
	 *
	 * @since 1.0.4
	 */
	do_action( 'gdpr_cache_after_stale_check' );
}
