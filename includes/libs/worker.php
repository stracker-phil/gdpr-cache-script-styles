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

add_action( 'gdpr_cache_worker', __NAMESPACE__ . '\run_background_tasks' );

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

		foreach ( $queue as $item_url ) {
			// Bail, if the URL is already enqueued.
			if ( $url === $item_url ) {
				return false;
			}
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

	// Lock the worker task.
	update_option( GDPR_CACHE_WORKER_LOCK, time() );

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

		// Update the process-lock
		update_option( GDPR_CACHE_WORKER_LOCK, time() );
	}

	// Unlock the worker task.
	delete_option( GDPR_CACHE_WORKER_LOCK );

	/**
	 * Action that fires after the background worker finished all enqueued
	 * tasks and cleaned up.
	 *
	 * @since 1.0.0
	 */
	do_action( 'gdpr_cache_after_worker' );
}

