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

add_filter( 'script_loader_src', __NAMESPACE__ . '\scan_external_scripts' );
add_filter( 'style_loader_src', __NAMESPACE__ . '\scan_external_styles' );

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
 * Scans enqueued scripts and replaces external src with a local one.
 *
 * @since 1.0.0
 *
 * @param string $source A script that might be local or external.
 *
 * @return string URL to the local script.
 */
function scan_external_scripts( string $source ) : string {
	if ( is_external_url( $source ) ) {
		$source = swap_to_local_asset( $source, 'script' );
	}

	return $source;
}


/**
 * Scans enqueued styles and replaces external href with a local one.
 *
 * @since 1.0.0
 *
 * @param string $source A style that might be local or external.
 *
 * @return string URL to the local style.
 */
function scan_external_styles( string $source ) : string {
	if ( is_external_url( $source ) ) {
		$source = swap_to_local_asset( $source, 'style' );
	}

	return $source;
}


/**
 * Caches the given URL in a local file and returns the URL to the local file.
 *
 * @since 1.0.0
 *
 * @param string $url  The URL to cache locally.
 * @param string $type Type of the asset [script|style]:
 *
 * @return string
 */
function swap_to_local_asset( string $url, string $type ) : string {
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
