<?php
/**
 * Data handling module (options, cache)
 *
 * @package GdprCache
 */

namespace GdprCache;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;


// ----------------------------------------------------------------------------

add_action( 'shutdown', __NAMESPACE__ . '\persist_last_used' );

// ----------------------------------------------------------------------------


/**
 * Returns an array of all cached external assets.
 *
 * @since 1.0.0
 * @return array List of external assets.
 */
function get_cached_data() {
	$data = wp_cache_get( 'data', 'gdpr-cache' );

	if ( ! is_array( $data ) ) {
		$data = get_option( GDPR_CACHE_OPTION );

		if ( ! is_array( $data ) ) {
			$data = [];
		}

		// Cache data to speed up next function call.
		wp_cache_set( 'data', $data, 'gdpr-cache' );
	}

	/**
	 * Filters the list of cached assets after it's read from the DB.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data The cache data that was read from the DB.
	 */
	return (array) apply_filters( 'gdpr_cache_get_data', $data );
}


/**
 * Sets the list of cached external assets.
 *
 * @since 1.0.0
 *
 * @param array $data List of external assets
 */
function set_cached_data( array $data ) {
	/**
	 * Filters the cache data before it's written to the DB.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data The cache data that's written to the DB.
	 */
	$data = (array) apply_filters( 'gdpr_cache_set_data', $data );

	// Write data to the cache.
	wp_cache_set( 'data', $data, 'gdpr-cache' );

	// Persist data to the DB.
	update_option( GDPR_CACHE_OPTION, $data );
}


/**
 * Remembers that the given URL is still used on the front-end.
 *
 * @since 1.0.4
 *
 * @param string $url    The URL that's accessed.
 * @param bool   $delete When true, the URL is deleted from the list. When
 *                       false, the timestamp of the URL is updated.
 *
 * @return void
 */
function set_last_used( $url, $delete = false ) {
	global $gdpr_cache_last_used;

	// We only want to track external URL usage.
	if ( ! is_external_url( $url ) ) {
		return;
	}

	if ( ! $gdpr_cache_last_used ) {
		$gdpr_cache_last_used = get_last_used();
	}

	if ( $delete ) {
		if ( isset( $gdpr_cache_last_used[ $url ] ) ) {
			unset( $gdpr_cache_last_used[ $url ] );
		}
	} else {
		$gdpr_cache_last_used[ $url ] = time();

		// Also set the usage timestamp of dependencies.
		foreach ( get_dependencies( $url ) as $ext_url => $local_url ) {
			$gdpr_cache_last_used[ $ext_url ] = time();
		}
	}

	// Store value to cache; we persist it to DB later.
	wp_cache_set( 'usage', $gdpr_cache_last_used, 'gdpr-cache' );
}


/**
 * Initializes the $gdpr_cache_last_used global variable.
 *
 * @since 1.0.4
 *
 * @return array List of last-used external URLs.
 */
function get_last_used() {
	global $gdpr_cache_last_used;

	// Try to load from cache, if possible.
	if ( ! $gdpr_cache_last_used ) {
		$gdpr_cache_last_used = wp_cache_get( 'usage', 'gdpr-cache' );
	}

	// No cache? Load from DB.
	if ( ! $gdpr_cache_last_used ) {
		$gdpr_cache_last_used = get_option( GDPR_CACHE_USAGE );
	}

	// No value in DB? Initialize with empty array.
	if ( ! $gdpr_cache_last_used ) {
		$gdpr_cache_last_used = [];
	}

	return $gdpr_cache_last_used;
}


/**
 * Store the "last used" timestamps to the DB.
 *
 * This function is called by the shutdown action, at the end of the request.
 *
 * @since 1.0.4
 * @return void
 */
function persist_last_used() {
	global $gdpr_cache_last_used;

	// When the usage details did not change, stop here.
	if ( empty( $gdpr_cache_last_used ) || ! is_array( $gdpr_cache_last_used ) ) {
		return;
	}

	update_option( GDPR_CACHE_USAGE, $gdpr_cache_last_used );
}


/**
 * Determines, how stale the given asset has become. The function returns the
 * number of hours since the asset was last used.
 *
 * A lower value means, the URL is fresh, and was recently served to a visitor.
 * A higher value indicates an asset that has become stale.
 *
 * For example: A value of 48 would indicate that the URL was last served
 * 48 hours ago, while a value of 0 means, that the URL was served within the
 * last hour.
 *
 * ---
 *
 * ### Why can assets become stale?
 *
 * This plugin constantly scans the page for new remote assets and adds them to
 * the cache, once they are detected. However, there is no way to detect assets
 * that were removed from the website - such as switching to a different
 * Google Font. That's why we use a staleness indication and delete all files
 * that were not served for a certain period of time.
 *
 * ---
 *
 * @since 1.0.4
 *
 * @param string $url The remote URL.
 *
 * @return int Number of hours since last asset usage. Returns -1 on failure.
 */
function get_asset_staleness( $url ) {
	$usage = get_last_used();

	if ( ! $url || ! is_external_url( $url ) ) {
		return - 1;
	}

	if ( ! array_key_exists( $url, $usage ) ) {
		set_last_used( $url );
	}

	$last_access = 0;
	if ( isset( $usage[ $url ] ) ) {
		$last_access = (int) $usage[ $url ];
	}

	if ( $last_access < 1 ) {
		return - 1;
	}

	return (int) ( ( time() - $last_access ) / HOUR_IN_SECONDS );
}


/**
 * Returns a list of all dependencies of cached assets.
 *
 * @since 1.0.4
 *
 * @param string $url Optional. When specified, the dependencies of this URL are
 *                    returned. If omitted, a list of all URLs and their
 *                    dependencies is returned.
 *
 * @return array List of the URLs dependencies, or a list of all URLs and their
 *     dependencies.
 */
function get_dependencies( $url = '' ) {
	$data = wp_cache_get( 'dependencies', 'gdpr-cache' );

	if ( ! is_array( $data ) ) {
		$data = get_option( GDPR_CACHE_DEPENDENCY );

		if ( ! is_array( $data ) ) {
			$data = [];
		}

		// Cache data to speed up next function call.
		wp_cache_set( 'dependencies', $data, 'gdpr-cache' );
	}

	/**
	 * Filters the list of external dependencies after it's read from the DB.
	 *
	 * @since 1.0.4
	 *
	 * @param array  $data The dependency list that was read from the DB.
	 * @param string $url  The URL-filter parameter.
	 */
	$data = (array) apply_filters( 'gdpr_cache_get_dependencies', $data, $url );

	if ( $url ) {
		return isset( $data[ $url ] ) ? $data[ $url ] : [];
	} else {
		return $data;
	}
}


/**
 * Updates the dependencies of the URL with a new list of files.
 *
 * @since 1.0.4
 *
 * @param string     $url_or_id    The remote URL of an asset that loads
 *                                 additional remote files, or an ID.
 * @param null|array $dependencies List of additional remote files that are
 *                                 loaded by the URL. Set to NULL or empty array
 *                                 to remove the dependency entry.
 *
 * @return void
 */
function set_dependencies( $url_or_id, $dependencies ) {
	$data = get_dependencies();

	if ( ! $dependencies ) {
		unset( $data[ $url_or_id ] );
	} elseif ( is_array( $dependencies ) ) {
		$data[ $url_or_id ] = $dependencies;
	}

	/**
	 * Filters the dependency list before it's written to the DB.
	 *
	 * @since 1.0.4
	 *
	 * @param array $data The dependency list that's written to the DB.
	 */
	$data = (array) apply_filters( 'gdpr_cache_set_dependencies', $data );

	// Write data to the cache.
	wp_cache_set( 'dependencies', $data, 'gdpr-cache' );

	// Persist data to the DB.
	update_option( GDPR_CACHE_DEPENDENCY, $data );
}


/**
 * Returns a list of all enqueued tasks in the background-worker queue.
 *
 * @since 1.0.0
 * @return array
 */
function get_worker_queue() {
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
	$queue = array_filter( $queue, __NAMESPACE__ . '\is_external_url' );

	update_option( GDPR_CACHE_QUEUE, $queue );
}


/**
 * Tests, if the background worker has items.
 *
 * @since 1.0.0
 * @return bool True if the worker queue is not empty.
 */
function has_worker_queue() {
	return count( get_worker_queue() ) > 0;
}
