<?php
/**
 * Asset caching module
 *
 * @package GdprCache
 */

namespace GdprCache;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;


// ----------------------------------------------------------------------------


/**
 * Returns an array of all cached external assets.
 *
 * @since 1.0.0
 * @return array List of external assets
 */
function get_cached_data() {
	$data = wp_cache_get( 'data', 'gdpr-cache' );

	if ( ! is_array( $data ) ) {
		$data = get_option( GDPR_CACHE_OPTION );

		if ( ! is_array( $data ) ) {
			$data = [];
			set_cached_data( $data );
			$data = wp_cache_get( 'data', 'gdpr-cache' );
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
 * Checks the given asset items status.
 *
 * @since 1.0.0
 *
 * @param string $url The remote URL.
 *
 * @return string The asset status [valid|expired|missing].
 */
function get_asset_status( $url ) {
	$data = get_cached_data();

	if ( empty( $data[ $url ] ) ) {
		// Item is not found in cache. It needs to be captured.
		return 'missing';
	}

	$item = $data[ $url ];

	if (
		empty( $item['file'] ) ||
		! file_exists( build_cache_file_path( $item['file'] ) )
	) {
		// Cache file deleted via FTP?
		return 'missing';
	}

	if ( empty( $item['expires'] ) || $item['expires'] < time() ) {
		// Asset is cached, but expired.
		return 'expired';
	}

	// The item is valid.
	return 'valid';
}


/**
 * Purges or invalidates the entire GDPR cache.
 *
 * Purge: All cache-files and DB states are deleted. For a few minutes, external
 * assets might be served, while the cache is re-populated.
 *
 * Invalidate: All cache items are expired but cache-files and DB details are
 * preserved. The website continues to serve cached files, but those files are
 * refreshed over the next couple of minutes.
 *
 * @since 1.0.0
 *
 * @param bool $invalidate When true, the cache is invalidated, and not flushed.
 *
 * @return void
 */
function flush_cache( $invalidate = false ) {
	if ( $invalidate ) {
		$data = get_cached_data();

		foreach ( $data as $url => $item ) {
			$data[ $url ]['expires'] = 0;
		}

		set_cached_data( $data );
	} else {
		// Flush the cache and empty DB item.
		set_cached_data( [] );

		// Also delete the background worker queue.
		set_worker_queue( [] );

		// Get a list of all files inside the cache-folder.
		$cache_dir = build_cache_file_path( '' );
		$files     = list_files( $cache_dir, 1 );
		$files     = array_filter( $files, 'is_file' );

		// Delete all cache files.
		array_map( 'unlink', $files );
	}
}


/**
 * Translates an external URL to a local URL.
 * Only works, when the external URL was cached via `cache_file_locally()`,
 * otherwise returns false.
 *
 * When an asset is cached but outdated, this function invalidates the cache
 * and enqueues a background task to refresh the asset, but return the local
 * URL of the outdated asset. The asset is usually refreshed within a few
 * seconds, so the outdated asset is only served for a very short time.
 *
 * @since 1.0.0
 *
 * @param string $url The external URL to translate.
 *
 * @return string|false Returns the URL to the local copy of the given external
 * asset, or false when that cache does not exist.
 */
function get_local_url( $url ) {
	$status = get_asset_status( $url );

	if ( 'missing' === $status ) {
		// Hard fail: Asset not cached yet.
		return enqueue_asset( $url );
	}

	if ( 'expired' === $status ) {
		// Soft fail: Asset cached but invalidated.
		// Enqueue cache-refresh of the asset but serve the outdated file.
		$res = enqueue_asset( $url );

		if ( $res ) {
			return $res;
		}
	}

	$cache = get_cached_data();
	$item  = $cache[ $url ];

	return build_cache_file_url( $item['file'] );
}


/**
 * Stores details about a cache file in the DB.
 *
 * @since 1.0.0
 *
 * @param string $url        The external URL.
 * @param string $file       Relative filename of the locally cached asset.
 * @param int    $expiration Lifetime of the cache file (in seconds).
 *
 * @return string Returns the URL to the local copy of the given asset.
 */
function set_local_item( $url, $file, $expiration = 0 ) {
	$cache = get_cached_data();

	if ( $expiration < 1 ) {
		$expiration = DAY_IN_SECONDS;
	}

	$cache[ $url ] = [
		'created' => time(),
		'expires' => time() + $expiration,
		'file'    => $file,
	];

	set_cached_data( $cache );

	return build_cache_file_url( $file );
}


/**
 * Downloads the specified URL and stores it in the local cache folder.
 *
 * This function updates the DB cache and returns the absolute URL to the
 * local cache file when done.
 *
 * @param string $url The external URL to cache.
 *
 * @return string|false URL to the local cache file. False, when the file could
 * not be downloaded to the local cache folder.
 */
function cache_file_locally( $url ) {
	$type    = get_url_type( $url );
	$timeout = 300;

	// Get HTTP request headers for requesting the remote file.
	$headers = get_remote_request_headers( $url, $type );

	// Add a CRC32 key to generate a unique filename based on URL and headers.
	$key = implode( ' ', [ $url, json_encode( $headers ) ] );

	$extension = implode( '.', [ '', crc32( $key ), $type ] );

	// Create a normalized base-name that reflects the remote asset URI.
	$base_name = substr( $url, strpos( $url, '//' ) + 2 );
	$base_name = preg_replace( '/\W+/', '.', $base_name );
	$base_name = substr( $base_name, 0, 250 - strlen( $extension ) );
	$base_name = strtolower( trim( $base_name, '.' ) );

	$filename   = $base_name . $extension;
	$cache_path = build_cache_file_path( $filename );

	// Download the remote asset to a local temp file.
	$resp = wp_safe_remote_get(
		$url,
		[
			'timeout'  => $timeout,
			'stream'   => true,
			'filename' => $cache_path,
			'headers'  => $headers,
		]
	);

	if (
		is_wp_error( $resp ) ||
		( file_exists( $cache_path ) && ! filesize( $cache_path ) )
	) {
		if ( file_exists( $cache_path ) ) {
			unlink( $cache_path );
		}

		return false;
	}

	// Check, if the remote server tells us a custom cache expiration time.
	$cc = wp_remote_retrieve_header( $resp, 'cache-control' );

	if ( $cc ) {
		$expires = (int) preg_replace( '/^.*max-age=(\d+).*$/', '$1', $cc );
	}

	if ( empty( $expires ) || $expires < 1 ) {
		$expires = DAY_IN_SECONDS;
	}

	// Scan the contents of the cached file to embed assets that are loaded
	// within that file.
	parse_cache_contents( $type, $cache_path );

	return set_local_item( $url, $filename, $expires );
}

/**
 * Returns an array with HTTP headers that should be used to request the
 * specified asset from the remote server.
 *
 * @since 1.0.1
 *
 * @param string $url  The external URL that will be requested.
 * @param string $type File-type extension of the remote file.
 *
 * @return string[] List of headers that are passed to `wp_remote_get()`.
 */
function get_remote_request_headers( $url, $type ) {
	$headers = [];

	// Insert the default user agent, if it's not empty.
	if ( GDPR_CACHE_DEFAULT_UA ) {
		$headers['User-agent'] = GDPR_CACHE_DEFAULT_UA;
	}

	/**
	 * Some assets return different content based on user-agent, so this
	 * filter allows us to inject those details.
	 *
	 * @since 1.0.1
	 *
	 * @param array  $headers The request headers.
	 * @param string $url     The URL that's requested.
	 * @param string $type    File type extension of the requested file.
	 */
	return (array) apply_filters(
		'gdpr_cache_remote_request_headers',
		$headers,
		$url,
		$type
	);
}
