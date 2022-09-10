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
 * Returns a list of all enqueued tasks in the background-worker queue.
 *
 * @since 1.0.0
 * @return array
 */
function get_worker_queue() : array {
	$queue = get_option( GDPR_CACHE_QUEUE );

	if ( ! is_array( $queue ) ) {
		$queue = [];
	}

	return $queue;
}


/**
 * Updates the tasks in the background-worker queue.
 *
 * @since 1.0.0
 *
 * @param array $queue The new worker queue to process.
 *
 * @return void
 */
function set_worker_queue( array $queue ) {
	update_option( GDPR_CACHE_QUEUE, $queue );
}


/**
 * Tests, if the background worker has items.
 *
 * @since 1.0.0
 * @return bool True if the worker queue is not empty.
 */
function has_worker_queue() : bool {
	return count( get_worker_queue() ) > 0;
}


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
 * Enqueues an external asset for local caching.
 *
 * @since 1.0.0
 *
 * @param string $url The external URL to download and cache.
 *
 * @return string|false Either false, when the asset was enqueued, or the local
 * URL when the asset was processed instantly.
 */
function enqueue_asset( string $url ) {
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
function is_worker_busy() : bool {
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

	// Lock the worker task.
	update_option( GDPR_CACHE_WORKER_LOCK, time() );

	$queue = get_worker_queue();

	// Process the worker queue.
	while ( $queue ) {
		$item_url = array_shift( $queue );

		if ( ! is_string( $item_url ) || ! $item_url ) {
			continue;
		}

		cache_file_locally( $item_url );

		set_worker_queue( $queue );

		// Update the process-lock
		update_option( GDPR_CACHE_WORKER_LOCK, time() );
	}

	// Unlock the worker task.
	delete_option( GDPR_CACHE_WORKER_LOCK );
}

