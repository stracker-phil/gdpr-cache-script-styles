<?php
/**
 * Scans the current request for external assets, attempts to replace them with
 * a locally cached asset
 *
 * @package GdprCache
 */

namespace GdprCache;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;


// ----------------------------------------------------------------------------

add_filter( 'script_loader_src', __NAMESPACE__ . '\scan_external_assets' );
add_filter( 'style_loader_src', __NAMESPACE__ . '\scan_external_assets' );

// ----------------------------------------------------------------------------


/**
 * Scans enqueued styles/scripts and replaces external sources with a local ones.
 *
 * @since 1.0.0
 *
 * @param string $source A script or style that might be local or external.
 *
 * @return string URL to the local script or style.
 */
function scan_external_assets( string $source ) : string {
	// Source is empty when asset is loaded via load-scripts.php or load-styles.php
	// Those assets are always local, and we can safely exit early.
	if ( ! $source ) {
		return $source;
	}

	if ( is_external_url( $source ) ) {
		$source = swap_to_local_asset( $source );
	}

	return $source;
}


/**
 * Caches the given URL in a local file and returns the URL to the local file.
 *
 * @since 1.0.0
 *
 * @param string $url The URL to cache locally.
 *
 * @return string
 */
function swap_to_local_asset( string $url ) : string {
	// When an invalid URL is provided, bail.
	if ( ! $url || false === strpos( $url, '//' ) ) {
		return $url;
	}

	/**
	 * Filter to allow short-circuiting the asset-swapping logic.
	 * When a string value is returned, that string is used as function return
	 * value without further checks.
	 *
	 * @since 1.0.0
	 *
	 * @param null|string $url      The URL to load in the visitor's browser, or
	 *                              NULL to use the default logic.
	 * @param string      $orig_url The original external asset URL.
	 */
	$pre_source = apply_filters( 'gdpr_cache_pre_swap_asset', null, $url );

	if ( is_string( $pre_source ) ) {
		return $pre_source;
	}

	$local_url = get_local_url( $url );

	/**
	 * Filter that allows modifying the asset URL before it's returned.
	 * This is useful if an empty local-URL should be returned instead of
	 * using the external asset.
	 *
	 * @since 1.0.0
	 *
	 * @param string|false $local_url The local cache URL, or false when the
	 *                                cache is not populated yet.
	 * @param string       $orig_url  The original external asset URL.
	 */
	$local_url = apply_filters( 'gdpr_cache_swap_asset', $local_url, $url );

	/*
	 * When the asset is still enqueued for capturing, the local URL is FALSE.
	 * In that case, we return the external URL so the website functions
	 * correctly until the background worker has processed the task queue.
	 */
	if ( false === $local_url ) {
		spawn_worker();

		return $url;
	}

	return $local_url;
}


/**
 * Scans the contents of the cached file to download and cache dependencies that
 * are loaded by the cached asset.
 *
 * @since 1.0.0
 *
 * @param string $type The file type that's parsed (css|js|ttf|...)
 * @param string $path Full path to the local cache file.
 *
 * @return void
 */
function parse_cache_contents( string $type, string $path ) {
	if ( 'css' !== $type ) {
		return;
	}

	/**
	 * The $matches array has 4 elements:
	 * [0] the full match, with url-prefix, the URI and the closing bracket
	 * [1] the "url(" prefix
	 * [2] the URI, with enclosing quotes <-- change this!
	 * [3] the closing bracket
	 *
	 * @param array $matches Array with 4 elements.
	 *
	 * @return string The full match with a different URI
	 */
	$parse_dependency = function ( array $matches ) : string {
		$uri = trim( $matches[2], '"\'' );

		$type = get_url_type( $uri );

		// Try to cache the external dependency.
		$local_uri = swap_to_local_asset( $uri, $type );

		if ( $local_uri ) {
			return $matches[1] . $local_uri . $matches[3];
		} else {
			return $matches[1] . $uri . $matches[3];
		}
	};

	// Read the file contents
	$contents = file_get_contents( $path );

	$contents = preg_replace_callback(
		'/([:\s]url\s*\()("[^"]*?"|\'[^\']*?\'|[^"\'][^)]*?)(\))/',
		$parse_dependency,
		$contents,
		- 1,
		$count
	);

	// In case an asset was downloaded and cached, update the local file.
	if ( $count ) {
		file_put_contents( $path, $contents );
	}
}
