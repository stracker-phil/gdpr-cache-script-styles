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

// add_action( 'wp_head', 'wp_die', 10 );

// ----------------------------------------------------------------------------


/**
 * Checks, if the given URL refers to an external asset (true), or if it points
 * to a local or relative source (false).
 *
 * @since 1.0.0
 *
 * @param string $url URL to check.
 *
 * @return bool True, if the provided URL is an external source.
 */
function is_external_url( string $url ) : bool {
	// Relative URLs are always local to the current domain.
	if ( 0 === strpos( $url, '/' ) ) {
		return false;
	}

	// Get protocol-relative
	$home_url = preg_replace( '/^\w+:/', '', home_url() );

	return false === strpos( $url, $home_url );
}


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

	// No need to modify an admin asset.
	if ( is_admin() ) {
		// return $source;
	}

	if ( 'style_loader_src' === current_filter() ) {
		$type = 'css';
	} else {
		$type = 'js';
	}

	if ( is_external_url( $source ) ) {
		$source = swap_to_local_asset( $source, $type );
	}

	return $source;
}


/**
 * Caches the given URL in a local file and returns the URL to the local file.
 *
 * @since 1.0.0
 *
 * @param string $url  The URL to cache locally.
 * @param string $type Type of the asset [js|css]:
 *
 * @return string
 */
function swap_to_local_asset( string $url, string $type ) : string {
	// When an invalid URL is provided, bail.
	if ( ! $url || false === strpos( $url, '//' ) ) {
		return $url;
	}

	$local_url = get_local_url( $url );

	if ( ! $local_url ) {
		$local_url = cache_file_locally( $url, $type );
	}

	// If caching the external asset failed, serve the original URL to avoid
	// interruptions of the website.
	if ( ! $local_url ) {
		// TODO: Notify the admin of the issue. They might need to make manual changes to comply with GDPR
		return $url;
	}

	return $local_url;
}
